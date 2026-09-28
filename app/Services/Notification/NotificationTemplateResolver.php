<?php

namespace App\Services\Notification;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\NotificationTemplate;

class NotificationTemplateResolver
{
    public function resolve(int $companyId, NotificationType $type, NotificationChannel $channel): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('type', $type->value)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderByRaw('company_id IS NULL')
            ->first();
    }
}