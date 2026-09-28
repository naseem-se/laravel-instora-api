<?php

namespace App\Services\Notification;

use App\Models\NotificationPreference;
use DateTimeInterface;

class NotificationPreferenceResolver
{
    /** A missing preference row means "use the migration's column defaults" - every channel/type enabled. */
    public function resolve(int $customerId): NotificationPreferenceDecision
    {
        $preference = NotificationPreference::where('customer_id', $customerId)->first();

        return new NotificationPreferenceDecision(
            emailEnabled: $preference?->email_enabled ?? true,
            whatsappEnabled: $preference?->whatsapp_enabled ?? true,
            smsEnabled: $preference?->sms_enabled ?? true,
            dueRemindersEnabled: $preference?->due_reminders_enabled ?? true,
            overdueRemindersEnabled: $preference?->overdue_reminders_enabled ?? true,
            paymentReceiptsEnabled: $preference?->payment_receipts_enabled ?? true,
            quietHoursStart: $this->timeString($preference?->quiet_hours_start),
            quietHoursEnd: $this->timeString($preference?->quiet_hours_end),
        );
    }

    private function timeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }
}