<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WebhookEventStatus: string
{
    use HasValues;

    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}