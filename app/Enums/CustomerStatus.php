<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CustomerStatus: string
{
    use HasValues;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
}