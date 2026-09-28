<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class RetryNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorize() call for 'notifications.manage' happens in the controller
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}