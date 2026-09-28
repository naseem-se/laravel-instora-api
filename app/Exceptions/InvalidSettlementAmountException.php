<?php

namespace App\Exceptions;

class InvalidSettlementAmountException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'The discount and late fee waived cannot exceed the outstanding balance.',
            'SETTLEMENT_AMOUNT_INVALID',
            422
        );
    }
}