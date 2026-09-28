<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WhatsAppProviderStatus: string
{
    use HasValues;

    case Active = 'active';
    case Inactive = 'inactive';
    case Error = 'error';
}