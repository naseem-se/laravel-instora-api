<?php

namespace App\Services\WhatsApp;

readonly class WhatsAppDispatchResult
{
    private function __construct(
        public bool $authorized,
        public ?string $skipReason,
        public ?SendResult $sendResult,
    ) {}

    public static function unauthorized(string $reason): self
    {
        return new self(false, $reason, null);
    }

    public static function dispatched(SendResult $result): self
    {
        return new self(true, null, $result);
    }
}