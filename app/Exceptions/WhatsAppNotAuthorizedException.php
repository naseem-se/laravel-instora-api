<?php

namespace App\Exceptions;

/** Maps a WhatsAppResolution skip reason to the exact message Errorhandling.md #32 documents for this scenario. */
class WhatsAppNotAuthorizedException extends BusinessException
{
    public function __construct(?string $reason)
    {
        parent::__construct(self::messageFor($reason), 'WHATSAPP_ACCESS_NOT_ALLOWED', 403);
    }

    private static function messageFor(?string $reason): string
    {
        return match ($reason) {
            'no_company_whatsapp_provider' => 'This company has not connected a WhatsApp number.',
            'no_authorized_whatsapp_provider' => 'No authorized WhatsApp provider is available for this company.',
            'shared_provider_not_authorized' => 'WhatsApp sending is not enabled for this company.',
            'shared_provider_user_not_authorized' => 'Your account is not authorized to send WhatsApp messages.',
            'company_suspended' => 'WhatsApp sending is disabled while this company is suspended.',
            default => 'WhatsApp sending is not currently available for this company.',
        };
    }
}