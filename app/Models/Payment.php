<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property int|null $installment_plan_id
 * @property int|null $invoice_id
 * @property string $payment_number
 * @property mixed $amount
 * @property \Illuminate\Support\Carbon $payment_date
 * @property PaymentStatus $status
 * @property int|null $received_by
 * @property-read Company|null $company
 * @property-read Invoice|null $invoice
 * @property-read InstallmentPlan|null $installmentPlan
 */
class Payment extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $guarded = [
        'id', 'company_id', 'payment_number', 'customer_id',
        'installment_plan_id', 'invoice_id', 'received_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class);
    }
}