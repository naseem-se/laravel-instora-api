<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'create' happens in the controller
    }

    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->requireCompanyId();

        return [
            'installment_plan_id' => [
                'required', 'integer',
                Rule::exists('installment_plans', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->whereNull('deleted_at');
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'cash_account_id' => [
                'nullable', 'integer',
                Rule::exists('cash_accounts', 'id')->where('company_id', $companyId),
            ],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}