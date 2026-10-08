<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWhatsAppEmbeddedSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:4096'],
            'waba_id' => ['required', 'string', 'regex:/^\d+$/', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'regex:/^\d+$/', 'max:255'],
        ];
    }
}
