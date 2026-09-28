<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentMethod: string
{
    use HasValues;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Card = 'card';
    case Easypaisa = 'easypaisa';
    case Jazzcash = 'jazzcash';
    case Other = 'other';
}