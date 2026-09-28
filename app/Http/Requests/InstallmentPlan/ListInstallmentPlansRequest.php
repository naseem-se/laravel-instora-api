<?php

namespace App\Http\Requests\InstallmentPlan;

use App\Enums\InstallmentPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInstallmentPlansRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(InstallmentPlanStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}