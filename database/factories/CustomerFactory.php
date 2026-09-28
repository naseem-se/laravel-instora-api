<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        // Test-only uniqueness helper - production numbering goes through
        // CustomerService::nextCustomerNumber(), which is company-scoped and
        // lock-protected. This just needs to never collide within a test run.
        static $sequence = 1;

        return [
            'company_id' => Company::factory(),
            'customer_number' => 'CUS-'.now()->year.'-'.str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT),
            'name' => fake()->name(),
            'phone' => fake()->numerify('03#########'),
            'cnic' => fake()->numerify('#####-#######-#'),
            'city' => fake()->city(),
            'status' => CustomerStatus::Active,
        ];
    }
}