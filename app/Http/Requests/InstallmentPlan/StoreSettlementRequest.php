<?php

namespace App\Http\Requests\InstallmentPlan;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for the 'settle' ability happens in the controller
    }

    public function rules(): array
    {
        return [
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'late_fee_waived' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'payment_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}