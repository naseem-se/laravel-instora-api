<?php

namespace App\Services\WhatsApp;

readonly class MessagePayload
{
    public function __construct(
        public string $recipient,
        public string $text,
    ) {}
}