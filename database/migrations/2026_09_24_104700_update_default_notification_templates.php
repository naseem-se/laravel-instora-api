<?php

use App\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use App\Support\DefaultNotificationTemplates;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DefaultNotificationTemplates::all() as $type => $content) {
            foreach (NotificationChannel::cases() as $channel) {
                NotificationTemplate::query()
                    ->whereNull('company_id')
                    ->where('type', $type)
                    ->where('channel', $channel)
                    ->where('is_active', true)
                    ->update([
                        'subject' => $channel === NotificationChannel::Email ? $content['subject'] : null,
                        'body' => DefaultNotificationTemplates::bodyFor($type, $channel),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Content-only update; previous wording is not restored.
    }
};
