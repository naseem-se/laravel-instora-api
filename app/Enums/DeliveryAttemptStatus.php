<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DeliveryAttemptStatus: string
{
    use HasValues;

    case Success = 'success';
    case Failed = 'failed';
}