<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InstallmentStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Waived = 'waived';
    case Cancelled = 'cancelled';
}