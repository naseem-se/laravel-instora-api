<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use App\Support\DefaultNotificationTemplates;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        NotificationTemplate::query()
            ->whereNull('company_id')
            ->where('type', NotificationType::PlanApproved->value)
            ->where('channel', NotificationChannel::Email->value)
            ->where('is_active', true)
            ->update([
                'subject' => DefaultNotificationTemplates::all()[NotificationType::PlanApproved->value]['subject'],
                'body' => DefaultNotificationTemplates::bodyFor(
                    NotificationType::PlanApproved->value,
                    NotificationChannel::Email,
                ),
            ]);
    }

    public function down(): void
    {
        // Content-only update; previous wording is not restored.
    }
};