<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        static $sequence = 1;

        return [
            'company_id' => Company::factory(),
            'payment_number' => 'PAY-'.now()->year.'-'.str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'payment_date' => now(),
            'amount' => 1000,
            'payment_method' => PaymentMethod::Cash,
            'received_by' => User::factory(),
            'status' => PaymentStatus::Completed,
        ];
    }
}