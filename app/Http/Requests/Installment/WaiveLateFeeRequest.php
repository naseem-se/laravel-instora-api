<?php

namespace App\Http\Requests\Installment;

use Illuminate\Foundation\Http\FormRequest;

class WaiveLateFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'installments.update' happens in the controller
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}