<?php

namespace App\Models;

use App\Enums\SubscriptionInvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscriptionInvoice extends Model
{
    protected $guarded = ['id', 'subscription_id', 'company_id', 'invoice_number', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => SubscriptionInvoiceStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscription::class, 'subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformSubscriptionPayment::class, 'invoice_id');
    }
}