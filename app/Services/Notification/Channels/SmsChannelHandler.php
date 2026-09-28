<?php

namespace App\Services\Notification\Channels;

use App\Models\NotificationLog;
use App\Services\Notification\Contracts\NotificationChannelHandler;
use App\Services\Notification\NotificationSendResult;

/** No SMS provider is defined anywhere in the spec - reserved for a future phase. */
class SmsChannelHandler implements NotificationChannelHandler
{
    public function send(NotificationLog $log): NotificationSendResult
    {
        return NotificationSendResult::skipped('channel_not_implemented');
    }
}