<?php

namespace App\Http\Requests\InstallmentPlan;

use App\Enums\FinancialChargeType;
use App\Enums\InstallmentFrequency;
use App\Enums\LateFeeType;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'create' happens in the controller
    }

    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->requireCompanyId();

        return [
            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->whereNull('deleted_at');
                }),
            ],
            'product_id' => [
                'nullable', 'integer',
                Rule::exists('products', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->where('status', 'active')->whereNull('deleted_at');
                }),
            ],
            'start_date' => ['required', 'date'],
            'principal_amount' => ['nullable', 'numeric', 'min:0.01'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'financial_charge_type' => ['required', Rule::in(FinancialChargeType::values())],
            'interest_type' => ['nullable', Rule::in(['flat'])],
            'interest_rate' => ['required_unless:financial_charge_type,none', 'numeric', 'min:0', 'max:1000'],
            'late_fee_type' => ['required', Rule::in(LateFeeType::values())],
            'late_fee_rate' => ['required_if:late_fee_type,percentage,percentage_per_day', 'numeric', 'min:0', 'max:100'],
            'late_fee_amount' => ['required_if:late_fee_type,fixed,per_day', 'numeric', 'min:0'],
            'maximum_late_fee' => ['nullable', 'numeric', 'min:0'],
            'installment_frequency' => ['required', Rule::in(InstallmentFrequency::values())],
            'custom_interval_days' => ['required_if:installment_frequency,custom', 'integer', 'min:1', 'max:365'],
            'number_of_installments' => ['required', 'integer', 'min:1', 'max:360'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $principal = (float) $this->input('principal_amount', 0);
            if (! $this->filled('principal_amount') && ! $this->filled('product_id')) {
                $validator->errors()->add('principal_amount', 'Select a product or enter a principal amount.');
            }
            $downPayment = (float) $this->input('down_payment', 0);

            if ($this->filled('principal_amount') && $downPayment >= $principal) {
                $validator->errors()->add(
                    'down_payment',
                    'The down payment must be less than the principal amount, leaving a positive balance to finance.'
                );
            }
        });
    }
}