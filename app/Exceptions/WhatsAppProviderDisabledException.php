<?php

namespace App\Exceptions;

class WhatsAppProviderDisabledException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('The configured WhatsApp provider is not currently active.', 'WHATSAPP_PROVIDER_DISABLED', 409);
    }
}