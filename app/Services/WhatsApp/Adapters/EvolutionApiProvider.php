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
 * Adapter for self-hosted Evolution API (https://github.com/EvolutionAPI/evolution-api).
 * Uses the WhatsApp Web protocol (Baileys) to send messages from a user's own phone number.
 * The Evolution API instance is shared across all companies; each company has its own session
 * identified by their WhatsApp provider record ID (e.g., "session_42").
 */
class EvolutionApiProvider implements WhatsAppProviderInterface
{
    use ClassifiesWhatsAppHttpErrors;

    public function __construct(
        private readonly WhatsAppProvider $config,
        private readonly SafeWhatsAppHttpClient $http,
    ) {}

    public function sendText(MessagePayload $payload): SendResult
    {
        return $this->sendMessage($payload->recipient, $payload->text);
    }

    /**
     * Evolution API does not use Meta-style templates.
     * Template payloads are rendered as plain text messages so existing notification
     * infrastructure continues to work without any changes.
     */
    public function sendTemplate(TemplatePayload $payload): SendResult
    {
        // Build a readable text from the template name + components if available.
        $text = $payload->templateName;
        if (! empty($payload->components)) {
            $params = collect($payload->components)
                ->flatMap(fn ($c) => $c['parameters'] ?? [])
                ->pluck('text')
                ->filter()
                ->values();

            if ($params->isNotEmpty()) {
                $text .= "\n" . $params->implode(' | ');
            }
        }

        return $this->sendMessage($payload->recipient, $text);
    }

    public function sendMedia(MediaPayload $payload): SendResult
    {
        $data = [
            'mediatype' => strtolower($payload->mediaType) === 'document' ? 'document' : 'image',
            'mimetype'  => strtolower($payload->mediaType) === 'document' ? 'application/pdf' : 'image/jpeg',
            'media'     => $payload->mediaUrl,
            'caption'   => $payload->caption ?? '',
        ];

        if ($payload->fileName) {
            $data['fileName'] = $payload->fileName;
        }

        return $this->sendMessage($payload->recipient, $data);
    }

    public function verifyConfiguration(): ProviderHealthResult
    {
        $sessionName = $this->sessionName();
        $baseUrl     = $this->baseUrl();
        $apiKey      = $this->resolveApiKey();

        if (! $baseUrl || ! $apiKey) {
            return ProviderHealthResult::unhealthy(
                'Evolution API base URL or API key is missing. Please contact your administrator.'
            );
        }

        try {
            $response = $this->http->get(
                "{$baseUrl}/instance/connectionState/{$sessionName}",
                $this->authHeaders(),
            );

            if ($response->successful()) {
                $state = $response->json('instance.state') ?? $response->json('state') ?? '';
                if (in_array($state, ['open', 'CONNECTED'], true)) {
                    return ProviderHealthResult::healthy();
                }

                return ProviderHealthResult::unhealthy(
                    "WhatsApp session is not connected (state: {$state}). Please scan the QR code again."
                );
            }

            $detail = $response->json('message') ?? $response->json('error') ?? '';
            return ProviderHealthResult::unhealthy(
                "Evolution API responded with HTTP {$response->status()}."
                . ($detail ? " Details: {$detail}" : '')
            );
        } catch (Throwable $e) {
            Log::error('EvolutionApi: connection check failed.', [
                'provider_id' => $this->config->id,
                'exception'   => $e->getMessage(),
            ]);

            return ProviderHealthResult::unhealthy('Unable to reach Evolution API: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function sendMessage(string $recipient, string|array $messageBody): SendResult
    {
        $sessionName = $this->sessionName();
        $baseUrl     = $this->baseUrl();
        $apiKey      = $this->resolveApiKey();

        if (! $baseUrl || ! $apiKey) {
            return SendResult::failed(
                'WHATSAPP_CREDENTIALS_INVALID',
                'Evolution API is not configured. Please contact your administrator.',
                retryable: false,
            );
        }

        // Normalise recipient to international format without "+".
        // e.g. "+923001234567" → "923001234567"
        //      "03001234567"   → "923001234567"  (Pakistan local)
        $number = ltrim($recipient, '+');
        if (str_starts_with($number, '0') && strlen($number) <= 11) {
            // Leading 0 = local Pakistani number, replace 0 with country code 92.
            $number = '92' . substr($number, 1);
        }

        // Build the flat payload Evolution API v2 expects.
        // Text:  { "number": "...", "text": "plain string" }
        // Media: { "number": "...", "mediatype": "IMAGE", ... }
        if (is_string($messageBody)) {
            $endpoint = "{$baseUrl}/message/sendText/{$sessionName}";
            $payload  = ['number' => $number, 'text' => $messageBody];
        } else {
            $endpoint = "{$baseUrl}/message/sendMedia/{$sessionName}";
            $payload  = array_merge(['number' => $number], $messageBody);
        }

        try {
            $response = $this->http->post($endpoint, $this->authHeaders(), $payload);

            if ($response->successful()) {
                $messageId = $response->json('key.id')
                    ?? $response->json('id')
                    ?? null;

                return SendResult::sent($messageId, $response->json() ?? []);
            }

            $errorMessage = $response->json('message')
                ?? $response->json('error')
                ?? 'Evolution API rejected the request.';
            $statusCode = $response->status();

            Log::warning('EvolutionApi: send failed.', [
                'provider_id' => $this->config->id,
                'http_status' => $statusCode,
                'error'       => $errorMessage,
                'session'     => $sessionName,
            ]);

            return SendResult::failed(
                $this->classifyErrorCode($statusCode),
                $errorMessage,
                $this->isRetryableStatus($statusCode),
                $response->json() ?? [],
            );
        } catch (Throwable $e) {
            report($e);

            return SendResult::failed(
                'WHATSAPP_NETWORK_ERROR',
                'Unable to reach Evolution API: ' . $e->getMessage(),
                retryable: true,
            );
        }
    }

    /** Session name = "session_{provider_id}" — unique per company. */
    public function sessionName(): string
    {
        return 'session_' . $this->config->id;
    }

    private function baseUrl(): ?string
    {
        return rtrim((string) config('services.evolution_api.url', ''), '/') ?: null;
    }

    private function resolveApiKey(): ?string
    {
        return (string) config('services.evolution_api.key', '') ?: null;
    }

    private function authHeaders(): array
    {
        return [
            'apikey'       => $this->resolveApiKey() ?? '',
            'Content-Type' => 'application/json',
        ];
    }
}
