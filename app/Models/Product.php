<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'company_id', 'status'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'cash_price' => 'decimal:2',
            'installment_price' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'status' => ActiveStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}