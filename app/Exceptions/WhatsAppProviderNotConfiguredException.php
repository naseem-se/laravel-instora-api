<?php

namespace App\Exceptions;

class WhatsAppProviderNotConfiguredException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('No WhatsApp provider is configured.', 'WHATSAPP_PROVIDER_NOT_CONFIGURED', 404);
    }
}