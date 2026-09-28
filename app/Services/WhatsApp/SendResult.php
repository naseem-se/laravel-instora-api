<?php

namespace App\Services\WhatsApp;

/** Normalized across every provider adapter - see Architecture.md #7 and Prompts.md #8. */
readonly class SendResult
{
    private function __construct(
        public bool $success,
        public ?string $providerMessageId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public bool $retryable,
        public array $rawMetadata,
    ) {}

    public static function sent(?string $providerMessageId, array $rawMetadata = []): self
    {
        return new self(true, $providerMessageId, null, null, false, $rawMetadata);
    }

    public static function failed(string $errorCode, string $errorMessage, bool $retryable, array $rawMetadata = []): self
    {
        return new self(false, null, $errorCode, $errorMessage, $retryable, $rawMetadata);
    }
}