<?php

namespace App\Models;

use App\Enums\DeliveryAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryAttempt extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryAttemptStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'response_metadata' => 'array',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class, 'notification_id');
    }
}