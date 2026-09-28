<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LateFeeType: string
{
    use HasValues;

    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case PerDay = 'per_day';
    case PercentagePerDay = 'percentage_per_day';
}