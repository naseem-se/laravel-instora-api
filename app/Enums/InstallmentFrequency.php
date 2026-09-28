<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InstallmentFrequency: string
{
    use HasValues;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Custom = 'custom';
}