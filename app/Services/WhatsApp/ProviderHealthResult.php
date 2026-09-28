<?php

namespace App\Services\WhatsApp;

readonly class ProviderHealthResult
{
    private function __construct(
        public bool $healthy,
        public ?string $message,
    ) {}

    public static function healthy(): self
    {
        return new self(true, null);
    }

    public static function unhealthy(string $message): self
    {
        return new self(false, $message);
    }
}