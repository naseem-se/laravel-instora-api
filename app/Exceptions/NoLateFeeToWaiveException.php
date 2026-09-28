<?php

namespace App\Exceptions;

class NoLateFeeToWaiveException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This installment has no outstanding late fee to waive.', 'NO_LATE_FEE_TO_WAIVE', 409);
    }
}