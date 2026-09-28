<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'request_metadata' => 'array',
            'response_metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class, 'notification_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }
}