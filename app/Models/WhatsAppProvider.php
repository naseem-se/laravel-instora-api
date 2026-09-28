<?php

namespace App\Models;

use App\Enums\WhatsAppProviderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $webhook_secret
 */
class WhatsAppProvider extends Model
{
    protected $table = 'whatsapp_providers';

    protected $guarded = ['id', 'company_id', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            // Encrypted at rest, never returned to the frontend - see Security.md #6.
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'is_default' => 'boolean',
            'status' => WhatsAppProviderStatus::class,
            'last_verified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'provider_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WhatsAppWebhookEvent::class, 'provider_id');
    }
}