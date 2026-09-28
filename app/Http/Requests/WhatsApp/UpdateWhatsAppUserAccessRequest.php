<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the super_admin route-group middleware
    }

    public function rules(): array
    {
        // Omitted/null enabled removes the per-user override entirely.
        return ['enabled' => ['nullable', 'boolean']];
    }
}