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
        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $payload->recipient,
            'type' => 'text',
            'text' => ['body' => $payload->text],
        ]);
    }

    public function sendTemplate(TemplatePayload $payload): SendResult
    {
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
        try {
            $response = $this->http->get($this->phoneNumberUrl(), $this->authHeaders());

            return $response->successful()
                ? ProviderHealthResult::healthy()
                : ProviderHealthResult::unhealthy("Provider responded with HTTP {$response->status()}.");
        } catch (Throwable $e) {
            return ProviderHealthResult::unhealthy('Unable to reach the provider: connection failed.');
        }
    }

    private function send(array $payload): SendResult
    {
        try {
            $response = $this->http->post("{$this->phoneNumberUrl()}/messages", $this->authHeaders(), $payload);

            if ($response->successful()) {
                return SendResult::sent($response->json('messages.0.id'), $response->json() ?? []);
            }

            return SendResult::failed(
                $this->classifyErrorCode($response->status()),
                $response->json('error.message') ?? 'The WhatsApp provider rejected the request.',
                $this->isRetryableStatus($response->status()),
                $response->json() ?? [],
            );
        } catch (Throwable $e) {
            report($e);

            return SendResult::failed('WHATSAPP_NETWORK_ERROR', 'Unable to reach the WhatsApp provider.', retryable: true);
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
            'Authorization' => 'Bearer '.($this->config->credentials['access_token'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }
}