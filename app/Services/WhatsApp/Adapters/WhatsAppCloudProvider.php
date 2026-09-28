<?php

namespace App\Services\WhatsApp\Adapters;

use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\Adapters\Concerns\ClassifiesWhatsAppHttpErrors;
use App\Services\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\Services\WhatsApp\MediaPayload;
use App\Services\WhatsApp\MessagePayload;
use App\Services\WhatsApp\ProviderHealthResult;
use App\Services\WhatsApp\SafeWhatsAppHttpClient;
use App\Services\WhatsApp\SendResult;
use App\Services\WhatsApp\TemplatePayload;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Meta's WhatsApp Cloud API request/response shape. */
class WhatsAppCloudProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly WhatsAppProvider $config,
        private readonly SafeWhatsAppHttpClient $http,
    ) {}

    use ClassifiesWhatsAppHttpErrors;

    public function sendText(MessagePayload $payload): SendResult
    {
        $tokenCheck = $this->validateAccessToken();
        if ($tokenCheck !== null) {
            return $tokenCheck;
        }

        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $payload->recipient,
            'type' => 'text',
            'text' => ['body' => $payload->text],
        ]);
    }

    public function sendTemplate(TemplatePayload $payload): SendResult
    {
        $tokenCheck = $this->validateAccessToken();
        if ($tokenCheck !== null) {
            return $tokenCheck;
        }

        $template = [
            'name' => $payload->templateName,
            'language' => ['code' => $payload->languageCode],
        ];

        if (! empty($payload->components)) {
            $template['components'] = $payload->components;
        }

        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $payload->recipient,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    public function sendMedia(MediaPayload $payload): SendResult
    {
        $tokenCheck = $this->validateAccessToken();
        if ($tokenCheck !== null) {
            return $tokenCheck;
        }

        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $payload->recipient,
            'type' => $payload->mediaType,
            $payload->mediaType => array_filter([
                'link' => $payload->mediaUrl,
                'caption' => $payload->caption,
            ]),
        ]);
    }

    public function verifyConfiguration(): ProviderHealthResult
    {
        // Check credentials can be decrypted and contain a valid token before calling the API.
        $accessToken = $this->resolveAccessToken();

        if ($accessToken === null || $accessToken === '') {
            Log::error('WhatsApp Cloud: access_token is empty or could not be decrypted.', [
                'provider_id' => $this->config->id,
                'credentials_is_array' => is_array($this->config->credentials),
                'credentials_keys' => is_array($this->config->credentials) ? array_keys($this->config->credentials) : 'NOT_ARRAY',
            ]);

            return ProviderHealthResult::unhealthy(
                'The access token is missing or could not be decrypted. '
                .'Please re-save your WhatsApp credentials.'
            );
        }

        try {
            $response = $this->http->get($this->phoneNumberUrl(), $this->authHeaders());

            if ($response->successful()) {
                return ProviderHealthResult::healthy();
            }

            $errorDetail = $response->json('error.message') ?? '';
            $statusCode = $response->status();

            Log::warning('WhatsApp Cloud: verification failed.', [
                'provider_id' => $this->config->id,
                'http_status' => $statusCode,
                'error' => $errorDetail,
                'url' => $this->phoneNumberUrl(),
            ]);

            if ($statusCode === 401) {
                return ProviderHealthResult::unhealthy(
                    'The access token was rejected by Meta (HTTP 401). '
                    .'The token may have expired or been revoked. Please generate a new token and re-save your credentials.'
                );
            }

            if ($statusCode === 403) {
                return ProviderHealthResult::unhealthy(
                    "Access forbidden (HTTP 403): {$errorDetail}. "
                    .'Verify the token has the required permissions for this phone number.'
                );
            }

            return ProviderHealthResult::unhealthy(
                "Provider responded with HTTP {$statusCode}."
                .($errorDetail ? " Details: {$errorDetail}" : '')
            );
        } catch (Throwable $e) {
            Log::error('WhatsApp Cloud: connection exception during verification.', [
                'provider_id' => $this->config->id,
                'exception' => $e->getMessage(),
            ]);

            return ProviderHealthResult::unhealthy('Unable to reach the provider: '.$e->getMessage());
        }
    }

    private function send(array $payload): SendResult
    {
        try {
            $response = $this->http->post("{$this->phoneNumberUrl()}/messages", $this->authHeaders(), $payload);

            if ($response->successful()) {
                return SendResult::sent($response->json('messages.0.id'), $response->json() ?? []);
            }

            $errorMessage = $response->json('error.message') ?? 'The WhatsApp provider rejected the request.';
            $statusCode = $response->status();

            Log::warning('WhatsApp Cloud: send failed.', [
                'provider_id' => $this->config->id,
                'http_status' => $statusCode,
                'error' => $errorMessage,
                'error_code' => $response->json('error.code'),
            ]);

            if ($statusCode === 401) {
                $errorMessage = 'The access token was rejected by Meta (HTTP 401). The token may have expired or been revoked.';
            }

            return SendResult::failed(
                $this->classifyErrorCode($statusCode),
                $errorMessage,
                $this->isRetryableStatus($statusCode),
                $response->json() ?? [],
            );
        } catch (Throwable $e) {
            report($e);

            return SendResult::failed('WHATSAPP_NETWORK_ERROR', 'Unable to reach the WhatsApp provider: '.$e->getMessage(), retryable: true);
        }
    }

    /**
     * Validate the access token is present before making API calls.
     * Returns a SendResult on failure, null if the token is valid.
     */
    private function validateAccessToken(): ?SendResult
    {
        $accessToken = $this->resolveAccessToken();

        if ($accessToken === null || $accessToken === '') {
            Log::error('WhatsApp Cloud: access_token is empty or could not be decrypted.', [
                'provider_id' => $this->config->id,
            ]);

            return SendResult::failed(
                'WHATSAPP_CREDENTIALS_INVALID',
                'The access token is missing or could not be decrypted. Please re-save your WhatsApp credentials.',
                retryable: false,
            );
        }

        return null;
    }

    /**
     * Safely resolve the access token from the encrypted credentials.
     * Returns null if credentials can't be decrypted or the token key is missing.
     */
    private function resolveAccessToken(): ?string
    {
        try {
            $credentials = $this->config->credentials;

            if (! is_array($credentials)) {
                return null;
            }

            $token = $credentials['access_token'] ?? null;

            return is_string($token) && $token !== '' ? $token : null;
        } catch (Throwable $e) {
            // Decryption failure - the APP_KEY likely changed since the credentials were stored.
            Log::error('WhatsApp Cloud: failed to decrypt credentials.', [
                'provider_id' => $this->config->id,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function phoneNumberUrl(): string
    {
        $version = $this->config->api_version ?: 'v20.0';

        return rtrim($this->config->base_url, '/')."/{$version}/{$this->config->phone_number_id}";
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.($this->resolveAccessToken() ?? ''),
            'Content-Type' => 'application/json',
        ];
    }
}