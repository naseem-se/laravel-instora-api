<?php

namespace App\Services\WhatsApp\Adapters\Concerns;

/** Shared HTTP status classification, per Errorhandling.md #21's retry rules. */
trait ClassifiesWhatsAppHttpErrors
{
    private function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private function classifyErrorCode(int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => 'WHATSAPP_CREDENTIALS_INVALID',
            $status === 404 => 'WHATSAPP_RECIPIENT_INVALID',
            $status === 429 => 'WHATSAPP_RATE_LIMITED',
            $status >= 500 => 'WHATSAPP_PROVIDER_ERROR',
            default => 'WHATSAPP_PROVIDER_ERROR',
        };
    }
}