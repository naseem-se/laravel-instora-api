<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $provider_id
 * @property WebhookEventStatus $status
 * @property \Illuminate\Support\Carbon|null $processed_at
 */
class WhatsAppWebhookEvent extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $table = 'whatsapp_webhook_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'status' => WebhookEventStatus::class,
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }
}