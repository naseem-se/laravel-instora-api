<?php

namespace App\Exceptions;

class PlanNotApprovableException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'This installment plan cannot be approved from its current status.',
            'PLAN_NOT_APPROVABLE',
            409
        );
    }
}