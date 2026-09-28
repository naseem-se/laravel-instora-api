<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppProvider;

readonly class WhatsAppResolution
{
    private function __construct(
        public bool $authorized,
        public ?WhatsAppProvider $provider,
        public ?string $skipReason,
        public ?string $source, // 'own' | 'shared' - powers the frontend's "effective sending source" display
    ) {}

    public static function authorized(WhatsAppProvider $provider, string $source): self
    {
        return new self(true, $provider, null, $source);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, null, $reason, null);
    }
}