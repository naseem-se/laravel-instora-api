<?php

namespace App\Services\WhatsApp;

readonly class MediaPayload
{
    public function __construct(
        public string $recipient,
        public string $mediaUrl,
        public string $mediaType, // image|document|video
        public ?string $caption = null,
    ) {}
}