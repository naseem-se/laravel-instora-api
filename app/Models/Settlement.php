<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $installment_plan_id
 * @property int $customer_id
 * @property int|null $payment_id
 * @property int $approved_by
 * @property string $outstanding_amount
 * @property string $discount_amount
 * @property string $late_fee_waived
 * @property string $final_amount
 */
class Settlement extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $guarded = ['id', 'company_id', 'payment_id', 'approved_by'];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'outstanding_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'late_fee_waived' => 'decimal:2',
            'final_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}