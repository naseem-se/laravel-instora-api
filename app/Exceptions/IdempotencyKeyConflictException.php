<?php

namespace App\Exceptions;

class IdempotencyKeyConflictException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'This idempotency key was already used with a different request body.',
            'IDEMPOTENCY_KEY_CONFLICT',
            409
        );
    }
}