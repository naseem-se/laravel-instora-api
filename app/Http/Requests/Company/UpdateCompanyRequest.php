<?php

namespace App\Http\Requests\Company;

use App\Enums\CompanyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the super_admin route-group middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'timezone'],
            'status' => ['sometimes', Rule::in(CompanyStatus::values())],
        ];
    }
}