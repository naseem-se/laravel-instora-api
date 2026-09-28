<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for the 'update' ability happens in the controller
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:5000'],
        ];
    }
}