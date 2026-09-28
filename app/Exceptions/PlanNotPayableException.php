<?php

namespace App\Exceptions;

class PlanNotPayableException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This installment plan is not in a payable status.', 'PLAN_NOT_PAYABLE', 409);
    }
}