<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum FinancialChargeType: string
{
    use HasValues;

    case None = 'none';
    case Markup = 'markup';
    case Interest = 'interest';
    case ServiceCharge = 'service_charge';
}