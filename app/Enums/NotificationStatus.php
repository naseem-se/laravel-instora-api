<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Queued = 'queued';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}