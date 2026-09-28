<?php

namespace App\Services\Notification\Channels;

use App\Models\NotificationLog;
use App\Services\Notification\Contracts\NotificationChannelHandler;
use App\Services\Notification\NotificationSendResult;
use App\Services\WhatsAppService;

/**
 * Replaces the Phase 10 stub. NotificationChannelResolver and
 * SendNotificationJob are completely unchanged - this class is the only
 * thing that swapped, satisfying the fail-closed contract established in
 * Phase 10 with a real resolver behind it now.
 *
 * If the notification template has a `whatsapp_template_name`, the handler
 * sends a Meta-approved template message (works outside the 24-hour window).
 * Otherwise it falls back to a free-form text message.
 */
class WhatsAppChannelHandler implements NotificationChannelHandler
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    public function send(NotificationLog $log): NotificationSendResult
    {
        $template = $log->template;

        if ($template && $template->whatsapp_template_name) {
            $dispatch = $this->sendViaTemplate($log, $template);
        } else {
            $dispatch = $this->sendViaText($log);
        }

        if (! $dispatch->authorized) {
            return NotificationSendResult::skipped($dispatch->skipReason);
        }

        $result = $dispatch->sendResult;

        return $result->success
            ? NotificationSendResult::sent($result->providerMessageId)
            : NotificationSendResult::failed($result->errorCode, $result->errorMessage, $result->retryable);
    }

    private function sendViaText(NotificationLog $log): \App\Services\WhatsApp\WhatsAppDispatchResult
    {
        return $this->whatsapp->sendText(
            notificationLogId: $log->id,
            companyId: $log->company_id,
            customerId: $log->customer_id,
            recipient: $log->recipient,
            text: $log->rendered_body ?? '',
        );
    }

    private function sendViaTemplate(NotificationLog $log, \App\Models\NotificationTemplate $template): \App\Services\WhatsApp\WhatsAppDispatchResult
    {
        // Build template components from the rendered body.
        // The rendered_body contains the final text with variables already substituted.
        // We pass it as a single body parameter so the template receives the full text.
        $components = [];
        if ($log->rendered_body) {
            $components = [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $log->rendered_body],
                    ],
                ],
            ];
        }

        return $this->whatsapp->sendTemplate(
            notificationLogId: $log->id,
            companyId: $log->company_id,
            customerId: $log->customer_id,
            recipient: $log->recipient,
            templateName: $template->whatsapp_template_name,
            languageCode: $template->whatsapp_template_language ?? 'en',
            components: $components,
        );
    }
}