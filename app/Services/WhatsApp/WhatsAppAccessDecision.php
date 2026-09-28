<?php

namespace App\Services\WhatsApp;

readonly class WhatsAppAccessDecision
{
    private function __construct(
        public bool $authorized,
        public ?string $reason,
    ) {}

    public static function authorized(): self
    {
        return new self(true, null);
    }

    public static function denied(string $reason): self
    {
        return new self(false, $reason);
    }
}