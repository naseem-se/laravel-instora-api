<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $costPrice = fake()->randomFloat(2, 1000, 50000);

        return [
            'company_id' => Company::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'name' => fake()->words(3, true),
            'cost_price' => $costPrice,
            'cash_price' => round($costPrice * 1.2, 2),
            'status' => ActiveStatus::Active,
        ];
    }
}