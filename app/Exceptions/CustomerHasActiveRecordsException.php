<?php

namespace App\Exceptions;

class CustomerHasActiveRecordsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'This customer has installment plans that are not completed and cannot be deleted.',
            'CUSTOMER_HAS_ACTIVE_RECORDS',
            409
        );
    }
}