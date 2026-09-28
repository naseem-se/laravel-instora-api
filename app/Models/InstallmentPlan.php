<?php

namespace App\Models;

use App\Enums\FinancialChargeType;
use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Enums\LateFeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property int|null $product_id
 * @property int $number_of_installments
 * @property string $plan_number
 * @property InstallmentPlanStatus $status
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property mixed $principal_amount
 * @property mixed $down_payment
 * @property mixed $financed_amount
 * @property mixed $interest_amount
 * @property mixed $total_amount
 * @property mixed $installment_amount
 * @property mixed $paid_amount
 * @property mixed $remaining_amount
 * @property int $created_by
 * @property int|null $approved_by
 * @property-read Company|null $company
 */
class InstallmentPlan extends Model
{
    use HasFactory, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $guarded = [
        'id', 'company_id', 'customer_id',
        'plan_number', 'financed_amount', 'interest_amount', 'total_amount', 'installment_amount',
        'paid_amount', 'remaining_amount', 'status',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'principal_amount' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'financed_amount' => 'decimal:2',
            'financial_charge_type' => FinancialChargeType::class,
            'interest_rate' => 'decimal:4',
            'interest_amount' => 'decimal:2',
            'late_fee_type' => LateFeeType::class,
            'late_fee_rate' => 'decimal:4',
            'late_fee_amount' => 'decimal:2',
            'maximum_late_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'installment_frequency' => InstallmentFrequency::class,
            'custom_interval_days' => 'integer',
            'installment_amount' => 'decimal:2',
            'status' => InstallmentPlanStatus::class,
            'approved_at' => 'datetime',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(InstallmentReschedule::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** One invoice per plan by convention (see InvoiceService), not a DB constraint. */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}