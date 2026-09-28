<?php

namespace App\Exceptions;

class PlanNotSettleableException extends BusinessException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'This installment plan cannot be settled in its current status.',
            'PLAN_NOT_SETTLEABLE',
            409
        );
    }
}