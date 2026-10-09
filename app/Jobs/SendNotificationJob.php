<?php

namespace App\Jobs;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\NotificationStatus;
use App\Exceptions\NotificationDeliveryFailedException;
use App\Models\NotificationDeliveryAttempt;
use App\Models\NotificationLog;
use App\Services\Notification\NotificationChannelResolver;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Redis;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(private readonly int $notificationLogId) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }


    public function handle(NotificationChannelResolver $channels): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (! $log) {
            return;
        }

        if ($log->channel === \App\Enums\NotificationChannel::WhatsApp) {
            $key = 'whatsapp_sending_' . $log->company_id;
            
            // Limit to 15 WhatsApp messages per minute per company to avoid bans
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 15)) {
                $this->release(\Illuminate\Support\Facades\RateLimiter::availableIn($key) ?: 10);
                return;
            }
            
            \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        }

        // Already terminal - a manual retry or a duplicate dispatch got
        // here first. Never re-send something already sent/skipped/cancelled.
        if (! in_array($log->status, [NotificationStatus::Pending, NotificationStatus::Queued], true)) {
            return;
        }

        $log->status = NotificationStatus::Sending;
        $log->save();

        $handler = $channels->resolve($log->channel);
        $result = $handler->send($log);

        if ($result->skipped) {
            $log->status = NotificationStatus::Skipped;
            $log->skip_reason = $result->skipReason;
            $log->save();

            return;
        }

        $attempt = new NotificationDeliveryAttempt();
        $attempt->notification_id = $log->id;
        $attempt->attempt_number = $log->deliveryAttempts()->count() + 1;
        $attempt->started_at = Carbon::now();;
        $attempt->completed_at = Carbon::now();
        $attempt->status = $result->success ? DeliveryAttemptStatus::Success : DeliveryAttemptStatus::Failed;
        $attempt->error_code = $result->errorCode;
        $attempt->error_message = $result->errorMessage;
        $attempt->save();

        if ($result->success) {
            $log->status = NotificationStatus::Sent;
            $log->sent_at = Carbon::now();
            $log->provider_message_id = $result->providerMessageId;
            $log->save();

            return;
        }

        $log->error_code = $result->errorCode;
        $log->error_message = $result->errorMessage;

        if ($result->retryable) {
            $log->status = NotificationStatus::Pending;
            $log->save();

            // Hands control back to the queue worker, which retries this
            // job per $tries/backoff() above. A permanent error skips
            // straight to 'failed' below instead - see Errorhandling.md #21/#33.
            throw new NotificationDeliveryFailedException($result->errorMessage ?? 'Delivery failed.');
        }

        $log->status = NotificationStatus::Failed;
        $log->save();
    }

    public function failed(\Throwable $e): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if ($log && $log->status !== NotificationStatus::Sent) {
            $log->status = NotificationStatus::Failed;
            $log->save();
        }
    }
}