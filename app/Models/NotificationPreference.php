<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $guarded = ['id', 'company_id', 'customer_id'];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'due_reminders_enabled' => 'boolean',
            'overdue_reminders_enabled' => 'boolean',
            'payment_receipts_enabled' => 'boolean',
            'quiet_hours_start' => 'datetime:H:i',
            'quiet_hours_end' => 'datetime:H:i',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}