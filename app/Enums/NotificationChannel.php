<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationChannel: string
{
    use HasValues;

    case Email = 'email';
    case Whatsapp = 'whatsapp';
    // case Sms = 'sms';
}