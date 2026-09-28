<?php

namespace App\Http\Requests\Product;

use App\Enums\ActiveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'viewAny' happens in the controller
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(ActiveStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(['name', '-name', 'created_at', '-created_at'])],
        ];
    }
}