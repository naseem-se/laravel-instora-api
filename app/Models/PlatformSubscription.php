<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscription extends Model
{
    protected $guarded = ['id', 'company_id', 'status', 'suspended_at'];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PlatformSubscriptionInvoice::class, 'subscription_id');
    }
}