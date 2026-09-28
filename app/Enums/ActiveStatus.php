<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ActiveStatus: string
{
    use HasValues;

    case Active = 'active';
    case Inactive = 'inactive';
}