<?php

namespace App\Http\Requests\ProductCategory;

use App\Enums\ActiveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'viewAny' happens in the controller
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(ActiveStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}