<?php

namespace App\Exceptions;

class OverpaymentNotAllowedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'This payment amount exceeds the outstanding balance on the plan.',
            'OVERPAYMENT_NOT_ALLOWED',
            409
        );
    }
}