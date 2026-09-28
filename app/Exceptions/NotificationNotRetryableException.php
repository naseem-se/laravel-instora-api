<?php

namespace App\Exceptions;

class NotificationNotRetryableException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Only failed or skipped notifications can be retried.',
            'NOTIFICATION_NOT_RETRYABLE',
            409
        );
    }
}