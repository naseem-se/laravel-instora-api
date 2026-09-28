<?php

namespace App\Services\Notification;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Carbon\Carbon;

readonly class NotificationPreferenceDecision
{
    public function __construct(
        public bool $emailEnabled,
        public bool $whatsappEnabled,
        public bool $smsEnabled,
        public bool $dueRemindersEnabled,
        public bool $overdueRemindersEnabled,
        public bool $paymentReceiptsEnabled,
        public ?string $quietHoursStart,
        public ?string $quietHoursEnd,
    ) {}

    public function channelEnabled(NotificationChannel $channel): bool
    {
        return match ($channel) {
            NotificationChannel::Email => $this->emailEnabled,
            NotificationChannel::Whatsapp => $this->whatsappEnabled,
            NotificationChannel::Sms => $this->smsEnabled,
        };
    }

    public function typeEnabled(NotificationType $type): bool
    {
        return match ($type) {
            NotificationType::InstallmentDueSoon, NotificationType::InstallmentDueToday => $this->dueRemindersEnabled,
            NotificationType::InstallmentOverdue => $this->overdueRemindersEnabled,
            NotificationType::PaymentReceived, NotificationType::PaymentReversed => $this->paymentReceiptsEnabled,
            default => true, // plan_approved / plan_settled have no dedicated toggle
        };
    }

    public function isWithinQuietHours(NotificationChannel $channel, Carbon $at): bool
    {
        if ($channel === NotificationChannel::Email) {
            return false;
        }

        if (! $this->quietHoursStart || ! $this->quietHoursEnd) {
            return false;
        }

        $time = $at->format('H:i:s');

        return $time >= $this->quietHoursStart && $time < $this->quietHoursEnd;
    }
}