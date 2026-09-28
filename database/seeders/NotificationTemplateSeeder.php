<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use App\Support\DefaultNotificationTemplates;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DefaultNotificationTemplates::all() as $type => $content) {
            foreach (NotificationChannel::cases() as $channel) {
                $subject = $channel === NotificationChannel::Email ? $content['subject'] : null;
                $body = DefaultNotificationTemplates::bodyFor($type, $channel);

                $active = NotificationTemplate::query()
                    ->whereNull('company_id')
                    ->where('type', $type)
                    ->where('channel', $channel)
                    ->where('is_active', true)
                    ->first();

                if ($active) {
                    $active->subject = $subject;
                    $active->body = $body;
                    $active->save();

                    continue;
                }

                NotificationTemplate::create([
                    'company_id' => null,
                    'type' => $type,
                    'channel' => $channel,
                    'version' => 1,
                    'subject' => $subject,
                    'body' => $body,
                    'is_active' => true,
                ]);
            }
        }
    }
}
