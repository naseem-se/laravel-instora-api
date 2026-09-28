<?php

namespace App\Services\Installment;

readonly class InstallmentCalculation
{
    /**
     * @param list<array{principal_cents:int, interest_cents:int, scheduled_cents:int}> $installmentBreakdown
     */
    public function __construct(
        public int $financedAmountCents,
        public int $interestAmountCents,
        public int $totalAmountCents,
        public int $installmentAmountCents,
        public array $installmentBreakdown,
    ) {}
}