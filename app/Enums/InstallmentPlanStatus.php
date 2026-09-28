<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InstallmentPlanStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Overdue = 'overdue';
    case Defaulted = 'defaulted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Settled = 'settled';
}