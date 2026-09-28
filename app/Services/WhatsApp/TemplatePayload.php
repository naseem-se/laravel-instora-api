<?php

namespace App\Services\WhatsApp;

readonly class TemplatePayload
{
    public function __construct(
        public string $recipient,
        public string $templateName,
        public string $languageCode,
        public array $components = [],
    ) {}
}