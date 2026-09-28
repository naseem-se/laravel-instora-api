<?php

namespace App\Services\Notification;

readonly class NotificationSendResult
{
    private function __construct(
        public bool $success,
        public bool $skipped,
        public ?string $skipReason,
        public ?string $providerMessageId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public bool $retryable,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, false, null, $providerMessageId, null, null, false);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, true, $reason, null, null, null, false);
    }

    public static function failed(string $errorCode, string $errorMessage, bool $retryable): self
    {
        return new self(false, false, null, null, $errorCode, $errorMessage, $retryable);
    }
}