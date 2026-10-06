<?php

namespace App\Http\Requests\Product;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'create' happens in the controller
    }

    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->requireCompanyId();

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->where('company_id', $companyId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'cash_price' => ['required', 'numeric', 'min:0'],
            'installment_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
        ];
    }
}