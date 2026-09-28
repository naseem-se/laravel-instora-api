<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $installment_plan_id
 * @property int $customer_id
 * @property int $installment_number
 * @property \Illuminate\Support\Carbon $due_date
 * @property mixed $principal_amount
 * @property mixed $interest_amount
 * @property mixed $scheduled_amount
 * @property mixed $paid_amount
 * @property mixed $remaining_amount
 * @property InstallmentStatus $status
 */
class Installment extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * The amount columns are only ever set by ScheduleGeneratorService (at
     * creation) or PaymentAllocationService (Phase 8) - both via direct
     * property assignment. Guarded here as defense in depth.
     */
    protected $guarded = [
        'id', 'company_id', 'installment_plan_id', 'customer_id',
        'principal_amount', 'interest_amount', 'late_fee_amount', 'scheduled_amount',
        'paid_amount', 'remaining_amount', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'scheduled_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'status' => InstallmentStatus::class,
            'paid_at' => 'datetime',
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

    public function reschedules(): HasMany
    {
        return $this->hasMany(InstallmentReschedule::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}