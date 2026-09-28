<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->lexify('CO????')),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'currency' => 'PKR',
            'timezone' => 'UTC',
            'status' => CompanyStatus::Active,
        ];
    }
}