<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SubscriptionInvoiceStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}