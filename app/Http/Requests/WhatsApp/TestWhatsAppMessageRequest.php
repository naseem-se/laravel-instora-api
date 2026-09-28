<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class TestWhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'whatsapp.send' happens in the controller
    }

    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:1000'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'template_language' => ['nullable', 'string', 'max:50'],
        ];
    }
}