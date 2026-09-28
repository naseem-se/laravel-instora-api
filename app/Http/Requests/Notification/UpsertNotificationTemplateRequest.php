<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'notifications.manage' happens in the controller
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(NotificationType::values())],
            'channel' => ['required', Rule::in(NotificationChannel::values())],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:5000'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_template_language' => ['nullable', 'string', 'max:50'],
        ];
    }
}