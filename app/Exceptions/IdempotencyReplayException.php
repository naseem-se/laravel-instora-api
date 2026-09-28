<?php

namespace App\Exceptions;

use Exception;

/**
 * Not an error - signals that a request with this Idempotency-Key already
 * completed, so the original stored response should be replayed verbatim
 * instead of running the operation again. Caught explicitly by the
 * controller, never routed through ApiExceptionRenderer.
 */
class IdempotencyReplayException extends Exception
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {
        parent::__construct('Idempotent replay.');
    }
}