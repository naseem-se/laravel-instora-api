<?php

namespace App\Http\Requests\InstallmentPlan;

use Illuminate\Foundation\Http\FormRequest;

class CancelInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'cancel' happens in the controller
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}