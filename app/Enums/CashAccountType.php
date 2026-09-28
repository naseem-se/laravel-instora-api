<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CashAccountType: string
{
    use HasValues;

    case Cash = 'cash';
    case Bank = 'bank';
    case MobileWallet = 'mobile_wallet';
}