<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentStatus: string
{
    use HasValues;

    case Completed = 'completed';
    case Reversed = 'reversed';
}