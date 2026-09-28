<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppCompanyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the super_admin route-group middleware
    }

    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean']];
    }
}