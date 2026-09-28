<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $installment_id
 * @property mixed $principal_amount
 * @property mixed $interest_amount
 * @property mixed $late_fee_amount
 * @property mixed $allocated_amount
 */
class PaymentAllocation extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}