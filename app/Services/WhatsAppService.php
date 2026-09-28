<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\MessagePayload;
use App\Services\WhatsApp\TemplatePayload;
use App\Services\WhatsApp\SendResult;
use App\Services\WhatsApp\WhatsAppDispatchResult;
use App\Services\WhatsApp\WhatsAppProviderAdapterFactory;
use App\Services\WhatsApp\WhatsAppProviderResolver;

class WhatsAppService
{
    public function __construct(
        private readonly WhatsAppProviderResolver $resolver,
        private readonly WhatsAppProviderAdapterFactory $adapters,
    ) {}

    public function sendText(
        int $notificationLogId,
        int $companyId,
        int $customerId,
        string $recipient,
        string $text,
        ?int $actorUserId = null,
    ): WhatsAppDispatchResult {
        $resolution = $this->resolver->resolve($companyId, $customerId, $actorUserId);

        if (! $resolution->authorized) {
            return WhatsAppDispatchResult::unauthorized($resolution->skipReason);
        }

        $adapter = $this->adapters->make($resolution->provider);
        $result = $adapter->sendText(new MessagePayload($recipient, $text));

        $this->logMessage($notificationLogId, $companyId, $resolution->provider, $recipient, $result, 'text');

        return WhatsAppDispatchResult::dispatched($result);
    }

    public function sendTemplate(
        int $notificationLogId,
        int $companyId,
        int $customerId,
        string $recipient,
        string $templateName,
        string $languageCode,
        array $components = [],
        ?int $actorUserId = null,
    ): WhatsAppDispatchResult {
        $resolution = $this->resolver->resolve($companyId, $customerId, $actorUserId);

        if (! $resolution->authorized) {
            return WhatsAppDispatchResult::unauthorized($resolution->skipReason);
        }

        $adapter = $this->adapters->make($resolution->provider);
        $result = $adapter->sendTemplate(new TemplatePayload($recipient, $templateName, $languageCode, $components));

        $this->logMessage($notificationLogId, $companyId, $resolution->provider, $recipient, $result, 'template');

        return WhatsAppDispatchResult::dispatched($result);
    }

    private function logMessage(int $notificationLogId, int $companyId, WhatsAppProvider $provider, string $recipient, SendResult $result, string $messageType = 'text'): void
    {
        $message = new WhatsAppMessage([
            'recipient' => $recipient,
            'message_type' => $messageType,
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->success ? 'sent' : 'failed',
            'response_metadata' => $result->rawMetadata,
        ]);

        $message->company_id = $companyId;
        $message->notification_id = $notificationLogId;
        $message->provider_id = $provider->id;
        $message->save();
    }
}