<?php

namespace App\Exceptions;

class PlanNotCancellableException extends BusinessException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'This installment plan cannot be cancelled from its current status.',
            'PLAN_NOT_CANCELLABLE',
            409
        );
    }
}