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

/**
 * Covers both a company's custom in-house API and any generic third-party
 * provider that doesn't need a Cloud-API-specific adapter - see the Phase 11
 * decision notes on why these aren't split into two classes.
 */
class GenericWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly WhatsAppProvider $config,
        private readonly SafeWhatsAppHttpClient $http,
    ) {}

    use ClassifiesWhatsAppHttpErrors;

    public function sendText(MessagePayload $payload): SendResult
    {
        $tokenCheck = $this->validateApiKey();
        if ($tokenCheck !== null) {
            return $tokenCheck;
        }

        return $this->send(['to' => $payload->recipient, 'type' => 'text', 'message' => $payload->text]);
    }

    public function sendTemplate(TemplatePayload $payload): SendResult
    {
        $tokenCheck = $this->validateApiKey();
        if ($tokenCheck !== null) {
            return $tokenCheck;
        }

        return $this->send([
            'to' => $payload->recipient,
            'type' => 'template',
            'template_name' => $payload->templateName,
            'variables' => $payload->components,
        ]);
    }

    public function sendMedia(MediaPayload $payload): SendResult
    {
        $tokenCheck = $this->validateApiKey();
        if ($tokenCheck !== null) {
            return $tokenCheck;
        }

        return $this->send([
            'to' => $payload->recipient,
            'type' => 'media',
            'media_url' => $payload->mediaUrl,
            'caption' => $payload->caption,
        ]);
    }

    public function verifyConfiguration(): ProviderHealthResult
    {
        $apiKey = $this->resolveApiKey();

        if ($apiKey === null || $apiKey === '') {
            Log::error('WhatsApp Generic: api_key is empty or could not be decrypted.', [
                'provider_id' => $this->config->id,
                'credentials_is_array' => is_array($this->config->credentials),
                'credentials_keys' => is_array($this->config->credentials) ? array_keys($this->config->credentials) : 'NOT_ARRAY',
            ]);

            return ProviderHealthResult::unhealthy(
                'The API key is missing or could not be decrypted. '
                .'Please re-save your WhatsApp credentials.'
            );
        }

        try {
            $response = $this->http->get($this->config->base_url, $this->authHeaders());

            if (in_array($response->status(), [401, 403], true)) {
                Log::warning('WhatsApp Generic: verification failed with auth error.', [
                    'provider_id' => $this->config->id,
                    'http_status' => $response->status(),
                ]);

                return ProviderHealthResult::unhealthy(
                    'The provider rejected the configured API key (HTTP '.$response->status().'). '
                    .'Please verify your API key and re-save your credentials.'
                );
            }

            return ProviderHealthResult::healthy();
        } catch (Throwable $e) {
            Log::error('WhatsApp Generic: connection exception during verification.', [
                'provider_id' => $this->config->id,
                'exception' => $e->getMessage(),
            ]);

            return ProviderHealthResult::unhealthy('Unable to reach the configured endpoint: '.$e->getMessage());
        }
    }

    private function send(array $payload): SendResult
    {
        try {
            $response = $this->http->post($this->config->base_url, $this->authHeaders(), $payload);

            if ($response->successful()) {
                return SendResult::sent($response->json('id') ?? $response->json('message_id'), $response->json() ?? []);
            }

            $statusCode = $response->status();

            Log::warning('WhatsApp Generic: send failed.', [
                'provider_id' => $this->config->id,
                'http_status' => $statusCode,
            ]);

            $errorMessage = 'The WhatsApp provider rejected the request.';
            if ($statusCode === 401) {
                $errorMessage = 'The API key was rejected by the provider (HTTP 401). Please verify your API key.';
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
     * Validate the API key is present before making API calls.
     * Returns a SendResult on failure, null if the key is valid.
     */
    private function validateApiKey(): ?SendResult
    {
        $apiKey = $this->resolveApiKey();

        if ($apiKey === null || $apiKey === '') {
            Log::error('WhatsApp Generic: api_key is empty or could not be decrypted.', [
                'provider_id' => $this->config->id,
            ]);

            return SendResult::failed(
                'WHATSAPP_CREDENTIALS_INVALID',
                'The API key is missing or could not be decrypted. Please re-save your WhatsApp credentials.',
                retryable: false,
            );
        }

        return null;
    }

    /**
     * Safely resolve the API key from the encrypted credentials.
     * Returns null if credentials can't be decrypted or the key is missing.
     */
    private function resolveApiKey(): ?string
    {
        try {
            $credentials = $this->config->credentials;

            if (! is_array($credentials)) {
                return null;
            }

            $key = $credentials['api_key'] ?? null;

            return is_string($key) && $key !== '' ? $key : null;
        } catch (Throwable $e) {
            // Decryption failure - the APP_KEY likely changed since the credentials were stored.
            Log::error('WhatsApp Generic: failed to decrypt credentials.', [
                'provider_id' => $this->config->id,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.($this->resolveApiKey() ?? ''),
            'Content-Type' => 'application/json',
        ];
    }
}