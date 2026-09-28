<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the super_admin route-group middleware
    }

    public function rules(): array
    {
        return [
            'monthly_fee' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'billing_anchor_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
        ];
    }
}