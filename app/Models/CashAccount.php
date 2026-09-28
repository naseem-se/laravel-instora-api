<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\CashAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    protected $guarded = ['id', 'company_id', 'current_balance'];

    protected function casts(): array
    {
        return [
            'type' => CashAccountType::class,
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'status' => ActiveStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }
}