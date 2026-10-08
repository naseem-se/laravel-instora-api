<?php

namespace App\Services\WhatsApp;

use App\Enums\NotificationStatus;
use App\Enums\WebhookEventStatus;
use App\Models\NotificationLog;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookService
{

    private const STATUS_RANK = [
        'pending' => 0, 'queued' => 0, 'sending' => 0,
        'sent' => 1, 'accepted' => 1, 'failed' => 1,
        'delivered' => 2,
        'read' => 3,
    ];

    public function verifySignature(WhatsAppProvider $provider, Request $request): bool
    {
        $secret = (string) $provider->webhook_secret;

        if (! $secret) {
            return false; // no secret configured - can never be trusted
        }

        if ($provider->provider_type === 'whatsapp_cloud') {
            $header = $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

            return hash_equals($expected, $header);
        }

        return hash_equals($secret, $request->header('X-Webhook-Secret', ''));
    }

    public function process(WhatsAppProvider $provider, array $payload): void
    {
        foreach ($this->extractEvents($provider, $payload) as $event) {
            DB::transaction(function () use ($provider, $event) {
                try {
                    $webhookEvent = new WhatsAppWebhookEvent([
                        'external_event_id' => $event['external_event_id'],
                        'event_type' => $event['status'],
                        'payload' => $event['raw'],
                    ]);
                    $webhookEvent->provider_id = $provider->id;
                    $webhookEvent->status = WebhookEventStatus::Received;
                    $webhookEvent->save();
                } catch (QueryException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }

                    // Same (provider_id, external_event_id) already
                    // processed - safely ignore per Security.md #13.
                    return;
                }

                $this->applyStatusUpdate($provider, $event, $webhookEvent);
                $webhookEvent->status = WebhookEventStatus::Processed;
                $webhookEvent->processed_at = Carbon::now();
                $webhookEvent->save();
            });
        }
    }

    private function applyStatusUpdate(WhatsAppProvider $provider, array $event, WhatsAppWebhookEvent $webhookEvent): void
    {
        $messageQuery = WhatsAppMessage::where('provider_id', $provider->id)
            ->where('provider_message_id', $event['message_id']);

        if ($provider->company_id !== null) {
            $messageQuery->where('company_id', $provider->company_id);
        }

        $message = $messageQuery->first();

        if (! $message) {
            $webhookEvent->status = WebhookEventStatus::Ignored;

            return;
        }

        $logQuery = NotificationLog::whereKey($message->notification_id);

        if ($provider->company_id !== null) {
            $logQuery->where('company_id', $provider->company_id);
        }

        $log = $logQuery->first();
        $newStatus = $this->mapStatus($event['status']);

        if (! $log || ! $newStatus) {
            $webhookEvent->status = WebhookEventStatus::Ignored;

            return;
        }

        if (! $this->isForwardProgress($log->status, $newStatus)) {
            return;
        }

        $log->status = $newStatus;
        match ($newStatus) {
            NotificationStatus::Delivered => $log->delivered_at = now(),
            NotificationStatus::Read => $log->read_at = now(),
            NotificationStatus::Failed => [
                $log->error_code = $event['error_code'] ?? 'WHATSAPP_PROVIDER_ERROR',
                $log->error_message = $event['error_message'] ?? 'The provider reported delivery failure.',
            ],
            default => null,
        };
        $log->save();

        $message->status = $event['status'];
        $message->save();
    }

    private function mapStatus(string $providerStatus): ?NotificationStatus
    {
        return match ($providerStatus) {
            'sent', 'accepted' => NotificationStatus::Sent,
            'delivered' => NotificationStatus::Delivered,
            'read' => NotificationStatus::Read,
            'failed' => NotificationStatus::Failed,
            default => null,
        };
    }

    private function isForwardProgress(NotificationStatus $current, NotificationStatus $incoming): bool
    {
        if ($current === NotificationStatus::Read) {
            return false;
        }

        return (self::STATUS_RANK[$incoming->value] ?? 0) >= (self::STATUS_RANK[$current->value] ?? 0);
    }

    /** @return list<array{external_event_id: string, message_id: ?string, status: ?string, error_code: ?string, error_message: ?string, raw: array}> */
    private function extractEvents(WhatsAppProvider $provider, array $payload): array
    {
        return $provider->provider_type === 'whatsapp_cloud'
            ? $this->extractCloudEvents($payload)
            : $this->extractGenericEvents($payload);
    }

    private function extractCloudEvents(array $payload): array
    {
        $events = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                foreach (($change['value']['statuses'] ?? []) as $status) {
                    if (! isset($status['id'], $status['status'])) {
                        continue;
                    }

                    $events[] = [
                        // Meta doesn't supply a single unique event ID for a
                        // status callback - message ID + status is the
                        // smallest genuinely-unique combination available.
                        'external_event_id' => "{$status['id']}:{$status['status']}",
                        'message_id' => $status['id'],
                        'status' => $status['status'],
                        'error_code' => $status['errors'][0]['code'] ?? null,
                        'error_message' => $status['errors'][0]['title'] ?? null,
                        'raw' => $status,
                    ];
                }
            }
        }

        return $events;
    }

    private function extractGenericEvents(array $payload): array
    {
        if (! isset($payload['message_id'], $payload['status'])) {
            return [];
        }

        return [[
            'external_event_id' => $payload['event_id'] ?? "{$payload['message_id']}:{$payload['status']}",
            'message_id' => $payload['message_id'],
            'status' => $payload['status'],
            'error_code' => $payload['error_code'] ?? null,
            'error_message' => $payload['error_message'] ?? null,
            'raw' => $payload,
        ]];
    }
}