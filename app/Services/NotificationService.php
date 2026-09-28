<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Exceptions\NotificationNotRetryableException;
use App\Enums\AuditAction;
use App\Jobs\SendNotificationJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notification\NotificationPreferenceResolver;
use App\Services\Notification\NotificationTemplateResolver;
use App\Support\TemplateRenderer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class NotificationService
{
    public function __construct(
        private readonly NotificationTemplateResolver $templates,
        private readonly NotificationPreferenceResolver $preferences,
        private readonly TemplateRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    public function send(
        int $companyId,
        int $customerId,
        NotificationType $type,
        NotificationChannel $channel,
        array $variables = [],
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $distinguisher = null,
    ): ?NotificationLog {
        try {
            return DB::transaction(function () use (
                $companyId, $customerId, $type, $channel, $variables, $referenceType, $referenceId, $distinguisher
            ) {
                $customer = Customer::find($customerId);
                if (! $customer) {
                    return null;
                }

                $company = Company::find($companyId);

                $recipient = match ($channel) {
                    NotificationChannel::Email => $customer->email,
                    NotificationChannel::Whatsapp, NotificationChannel::Sms => $customer->phone,
                };

                $idempotencyKey = $this->buildIdempotencyKey(
                    $companyId, $customerId, $type, $channel, $referenceType, $referenceId, $distinguisher
                );

                $log = new NotificationLog();
                $log->company_id = $companyId;
                $log->customer_id = $customerId;
                $log->installment_id = $referenceType === 'installment' ? $referenceId : null;
                $log->invoice_id = $referenceType === 'invoice' ? $referenceId : null;
                $log->payment_id = $referenceType === 'payment' ? $referenceId : null;
                $log->type = $type;
                $log->channel = $channel;
                $log->recipient = $recipient ?? '';
                $log->idempotency_key = $idempotencyKey;
                $log->status = NotificationStatus::Pending;

                try {
                    $log->save();
                } catch (QueryException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }

                    // This exact business event already created a log -
                    // the scheduler restarting, or a duplicate call, found
                    // it first. Nothing new to send.
                    return NotificationLog::where('idempotency_key', $idempotencyKey)->first();
                }

                if (! $recipient) {
                    $log->status = NotificationStatus::Skipped;
                    $log->skip_reason = 'recipient_missing';
                    $log->save();

                    return $log;
                }

                $decision = $this->preferences->resolve($customerId);

                if (! $decision->channelEnabled($channel) || ! $decision->typeEnabled($type)) {
                    $log->status = NotificationStatus::Skipped;
                    $log->skip_reason = 'customer_preference_disabled';
                    $log->save();

                    return $log;
                }

                $template = $this->templates->resolve($companyId, $type, $channel);

                if (! $template) {
                    $log->status = NotificationStatus::Failed;
                    $log->error_code = 'TEMPLATE_MISSING';
                    $log->error_message = 'No active template is configured for this notification type and channel.';
                    $log->save();

                    return $log;
                }

                $log->template_id = $template->id;

                $allVariables = array_merge([
                    'customer_name' => $customer->name,
                    'company_name' => $company?->name ?? '',
                    'company_phone' => $company?->phone ?? '',
                    'company_email' => $company?->email ?? '',
                ], $variables);

                $log->rendered_subject = $template->subject ? $this->renderer->render($template->subject, $allVariables) : null;
                $log->rendered_body = $this->renderer->render($template->body, $allVariables);

                /** @var \Illuminate\Support\Carbon $scheduledFor */
                $scheduledFor = now();
                if ($decision->isWithinQuietHours($channel, $scheduledFor) && $decision->quietHoursEnd) {
                    $scheduledFor = $scheduledFor->copy()->setTimeFromTimeString($decision->quietHoursEnd);
                }
                $log->scheduled_for = $scheduledFor;
                $log->status = NotificationStatus::Queued;
                $log->save();

                SendNotificationJob::dispatch($log->id)->delay($scheduledFor)->afterCommit();

                return $log;
            });
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function retry(NotificationLog $log, User $actor, ?string $reason = null): NotificationLog
    {
        if (! in_array($log->status, [NotificationStatus::Failed, NotificationStatus::Skipped], true)) {
            throw new NotificationNotRetryableException();
        }

        return DB::transaction(function () use ($log, $actor, $reason) {
            $log->status = NotificationStatus::Queued;
            $log->error_code = null;
            $log->error_message = null;
            $log->skip_reason = null;
            $log->save();

            SendNotificationJob::dispatch($log->id)->afterCommit();

            $this->audit->log(
                AuditAction::NotificationRetried->value,
                entity: $log,
                newValues: ['status' => 'queued', 'reason' => $reason],
                companyId: $log->company_id,
                userId: $actor->id,
            );

            return $log;
        });
    }

    private function buildIdempotencyKey(
        int $companyId,
        int $customerId,
        NotificationType $type,
        NotificationChannel $channel,
        ?string $referenceType,
        ?int $referenceId,
        ?string $distinguisher,
    ): string {
        return implode(':', array_filter(
            [$companyId, $customerId, $type->value, $channel->value, $referenceType, $referenceId, $distinguisher],
            fn ($v) => $v !== null
        ));
    }
}