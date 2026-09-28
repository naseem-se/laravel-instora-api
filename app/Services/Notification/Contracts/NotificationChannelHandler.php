<?php

namespace App\Services\Notification\Contracts;

use App\Models\NotificationLog;
use App\Services\Notification\NotificationSendResult;

interface NotificationChannelHandler
{
    public function send(NotificationLog $log): NotificationSendResult;
}