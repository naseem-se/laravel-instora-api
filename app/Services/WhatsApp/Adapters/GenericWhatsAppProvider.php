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
        return $this->send(['to' => $payload->recipient, 'type' => 'text', 'message' => $payload->text]);
    }

    public function sendTemplate(TemplatePayload $payload): SendResult
    {
        return $this->send([
            'to' => $payload->recipient,
            'type' => 'template',
            'template_name' => $payload->templateName,
            'variables' => $payload->components,
        ]);
    }

    public function sendMedia(MediaPayload $payload): SendResult
    {
        return $this->send([
            'to' => $payload->recipient,
            'type' => 'media',
            'media_url' => $payload->mediaUrl,
            'caption' => $payload->caption,
        ]);
    }

    public function verifyConfiguration(): ProviderHealthResult
    {
        try {
            $response = $this->http->get($this->config->base_url, $this->authHeaders());

            if (in_array($response->status(), [401, 403], true)) {
                return ProviderHealthResult::unhealthy('The provider rejected the configured API key.');
            }

            return ProviderHealthResult::healthy();
        } catch (Throwable $e) {
            return ProviderHealthResult::unhealthy('Unable to reach the configured endpoint.');
        }
    }

    private function send(array $payload): SendResult
    {
        try {
            $response = $this->http->post($this->config->base_url, $this->authHeaders(), $payload);

            if ($response->successful()) {
                return SendResult::sent($response->json('id') ?? $response->json('message_id'), $response->json() ?? []);
            }

            return SendResult::failed(
                $this->classifyErrorCode($response->status()),
                'The WhatsApp provider rejected the request.',
                $this->isRetryableStatus($response->status()),
                $response->json() ?? [],
            );
        } catch (Throwable $e) {
            report($e);

            return SendResult::failed('WHATSAPP_NETWORK_ERROR', 'Unable to reach the WhatsApp provider.', retryable: true);
        }
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.($this->config->credentials['api_key'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }
}