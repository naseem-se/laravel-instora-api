<?php

namespace App\Exceptions;

class PlanNotDeletableException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Only completed installment plans can be deleted.',
            'PLAN_NOT_DELETABLE',
            409
        );
    }
}