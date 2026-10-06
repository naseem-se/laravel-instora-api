<?php

namespace App\Http\Requests\Sale;

use App\Enums\FinancialChargeType;
use App\Enums\InstallmentFrequency;
use App\Enums\LateFeeType;
use App\Enums\PaymentMethod;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->requireCompanyId();

        return [
            'customer_id' => ['nullable', 'required_without:new_customer', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'new_customer' => ['nullable', 'required_without:customer_id', 'array'],
            'new_customer.name' => ['required_with:new_customer', 'string', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:50'],
            'new_customer.cnic' => ['nullable', 'string', 'max:50'],
            'new_customer.email' => ['nullable', 'email', 'max:255'],
            'new_customer.address' => ['nullable', 'string', 'max:2000'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)->where('status', 'active')->whereNull('deleted_at')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('inventory_warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'payment_type' => ['required', Rule::in(['cash', 'installment'])],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'additional_charges' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'installment' => ['required_if:payment_type,installment', 'array'],
            'installment.start_date' => ['required_if:payment_type,installment', 'date'],
            'installment.financial_charge_type' => ['required_if:payment_type,installment', Rule::in(FinancialChargeType::values())],
            'installment.interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'installment.late_fee_type' => ['required_if:payment_type,installment', Rule::in(LateFeeType::values())],
            'installment.late_fee_rate' => ['required_if:installment.late_fee_type,percentage,percentage_per_day', 'numeric', 'min:0', 'max:100'],
            'installment.late_fee_amount' => ['required_if:installment.late_fee_type,fixed,per_day', 'numeric', 'min:0'],
            'installment.maximum_late_fee' => ['nullable', 'numeric', 'min:0'],
            'installment.installment_frequency' => ['required_if:payment_type,installment', Rule::in(InstallmentFrequency::values())],
            'installment.custom_interval_days' => ['required_if:installment.installment_frequency,custom', 'integer', 'min:1', 'max:365'],
            'installment.number_of_installments' => ['required_if:payment_type,installment', 'integer', 'min:1', 'max:360'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('payment_type') !== 'installment') {
                if ($this->filled('down_payment')) {
                    $validator->errors()->add('down_payment', 'Down payment is only used for installment sales.');
                }
                return;
            }

            if ($this->input('installment.financial_charge_type') !== 'none' && ! $this->filled('installment.interest_rate')) {
                $validator->errors()->add('installment.interest_rate', 'Enter a finance charge rate.');
            }
            if (in_array($this->input('installment.late_fee_type'), ['percentage', 'percentage_per_day'], true) && ! $this->filled('installment.late_fee_rate')) {
                $validator->errors()->add('installment.late_fee_rate', 'Enter a late fee rate.');
            }
            if (in_array($this->input('installment.late_fee_type'), ['fixed', 'per_day'], true) && ! $this->filled('installment.late_fee_amount')) {
                $validator->errors()->add('installment.late_fee_amount', 'Enter a late fee amount.');
            }
        });
    }
}