<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'viewAny' happens in the controller
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer'],
            'installment_plan_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(PaymentStatus::values())],
            'payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}