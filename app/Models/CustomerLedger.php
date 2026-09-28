<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property mixed $debit
 * @property mixed $credit
 * @property mixed $balance
 * @property int $created_by
 */
class CustomerLedger extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    const UPDATED_AT = null;

    protected $table = 'customer_ledger';

    protected $guarded = ['id', 'company_id', 'balance', 'created_by'];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
            'transaction_date' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}