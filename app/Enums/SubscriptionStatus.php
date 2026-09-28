<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SubscriptionStatus: string
{
    use HasValues;

    case Active = 'active';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}