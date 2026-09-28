<?php

namespace App\Exceptions;

class IdempotencyKeyInProgressException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'A request with this idempotency key is still being processed. Please wait and try again.',
            'IDEMPOTENCY_KEY_IN_PROGRESS',
            409
        );
    }
}