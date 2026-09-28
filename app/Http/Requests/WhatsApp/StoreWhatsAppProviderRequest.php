<?php

namespace App\Http\Requests\WhatsApp;

use App\Rules\SafeProviderUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'whatsapp.configure' happens in the controller
    }

    public function rules(): array
    {
        return [
            'provider_type' => ['required', Rule::in(['whatsapp_cloud', 'generic'])],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'starts_with:https://', new SafeProviderUrl()],
            'api_version' => ['nullable', 'string', 'max:100'],
            'phone_number_id' => ['required_if:provider_type,whatsapp_cloud', 'nullable', 'string', 'max:255'],
            'business_account_id' => ['nullable', 'string', 'max:255'],
            'sender_number' => ['nullable', 'string', 'max:50'],
            'credentials' => ['array'],
            'credentials.access_token' => ['nullable', 'string'],
            'credentials.api_key' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
        ];
    }
}