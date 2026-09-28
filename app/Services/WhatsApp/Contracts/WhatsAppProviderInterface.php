<?php

namespace App\Services\WhatsApp\Contracts;

use App\Services\WhatsApp\MediaPayload;
use App\Services\WhatsApp\MessagePayload;
use App\Services\WhatsApp\ProviderHealthResult;
use App\Services\WhatsApp\SendResult;
use App\Services\WhatsApp\TemplatePayload;

interface WhatsAppProviderInterface
{
    public function sendText(MessagePayload $payload): SendResult;

    public function sendTemplate(TemplatePayload $payload): SendResult;

    public function sendMedia(MediaPayload $payload): SendResult;

    public function verifyConfiguration(): ProviderHealthResult;
}