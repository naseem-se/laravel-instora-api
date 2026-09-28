<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InvoiceStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Issued = 'issued';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}