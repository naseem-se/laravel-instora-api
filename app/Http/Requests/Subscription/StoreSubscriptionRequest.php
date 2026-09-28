<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the super_admin route-group middleware
    }

    public function rules(): array
    {
        return [
            'monthly_fee' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'billing_anchor_day' => ['required', 'integer', 'min:1', 'max:28'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ];
    }
}