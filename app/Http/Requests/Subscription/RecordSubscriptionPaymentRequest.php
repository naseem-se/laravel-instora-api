<?php

namespace App\Http\Requests\Subscription;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the super_admin route-group middleware
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}