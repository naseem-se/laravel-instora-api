<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'notifications.view' happens in the controller
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(NotificationStatus::values())],
            'channel' => ['nullable', Rule::in(NotificationChannel::values())],
            'type' => ['nullable', Rule::in(NotificationType::values())],
            'customer_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}