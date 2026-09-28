<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'users.update' happens in the controller
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(UserStatus::values())],
            'role' => ['sometimes', Rule::in(['Company Admin', 'Employee'])],
        ];
    }
}