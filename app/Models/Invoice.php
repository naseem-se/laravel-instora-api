<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property int|null $installment_plan_id
 * @property string $invoice_number
 * @property mixed $subtotal
 * @property mixed $discount
 * @property mixed $tax
 * @property mixed $interest
 * @property mixed $late_fee
 * @property mixed $total_amount
 * @property mixed $paid_amount
 * @property mixed $balance_amount
 * @property InvoiceStatus $status
 * @property int $created_by
 * @property-read Company|null $company
 * @property-read InstallmentPlan|null $installmentPlan
 */
class Invoice extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $guarded = [
        'id', 'company_id', 'invoice_number', 'customer_id', 'installment_plan_id',
        'paid_amount', 'balance_amount', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'tax' => 'decimal:2',
            'interest' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}