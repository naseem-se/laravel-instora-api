<?php

namespace App\Exceptions;

class InvoiceNotPayableException extends BusinessException
{
    public function __construct(string $message = 'This invoice is not available for payment.')
    {
        parent::__construct($message, 'INVOICE_NOT_PAYABLE', 409);
    }
}
