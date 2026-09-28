<?php

namespace App\Services\Notification;

use App\Enums\NotificationChannel;
use App\Services\Notification\Channels\EmailChannelHandler;
use App\Services\Notification\Channels\SmsChannelHandler;
use App\Services\Notification\Channels\WhatsAppChannelHandler;
use App\Services\Notification\Contracts\NotificationChannelHandler;

class NotificationChannelResolver
{
    public function __construct(
        private readonly EmailChannelHandler $email,
        private readonly WhatsAppChannelHandler $whatsapp,
        private readonly SmsChannelHandler $sms,
    ) {}

    public function resolve(NotificationChannel $channel): NotificationChannelHandler
    {
        return match ($channel) {
            NotificationChannel::Email => $this->email,
            NotificationChannel::Whatsapp => $this->whatsapp,
            NotificationChannel::Sms => $this->sms,
        };
    }
}