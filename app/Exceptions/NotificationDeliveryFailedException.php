<?php

namespace App\Exceptions;

use Exception;

/**
 * Internal signal to the queue worker to retry, per $tries/backoff() on
 * SendNotificationJob. Never rendered to HTTP - jobs don't go through
 * ApiExceptionRenderer, so this deliberately does not extend BusinessException.
 */
class NotificationDeliveryFailedException extends Exception
{
}