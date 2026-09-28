<?php

namespace App\Exceptions;

class PaymentAlreadyReversedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This payment has already been reversed.', 'PAYMENT_ALREADY_REVERSED', 409);
    }
}