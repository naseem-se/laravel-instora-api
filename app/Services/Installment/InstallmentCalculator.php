<?php

namespace App\Services\Installment;

use App\Enums\FinancialChargeType;
use App\Support\Money;

class InstallmentCalculator
{
    public function calculate(
        int $principalCents,
        int $downPaymentCents,
        FinancialChargeType $chargeType,
        ?string $interestRate,
        int $numberOfInstallments,
    ): InstallmentCalculation {
        $financedCents = $principalCents - $downPaymentCents;

        // Charges are based on the full product price. The down payment only
        // reduces the principal that remains in the installment schedule.
        $interestCents = $chargeType === FinancialChargeType::None
            ? 0
            : Money::percentageOf($principalCents, $interestRate ?? '0');

        $totalCents = $financedCents + $interestCents;

        $principalShares = Money::splitEvenly($financedCents, $numberOfInstallments);
        $interestShares = Money::splitEvenly($interestCents, $numberOfInstallments);

        $breakdown = [];

        for ($i = 0; $i < $numberOfInstallments; $i++) {
            $breakdown[] = [
                'principal_cents' => $principalShares[$i],
                'interest_cents' => $interestShares[$i],
                'scheduled_cents' => $principalShares[$i] + $interestShares[$i],
            ];
        }

        return new InstallmentCalculation(
            financedAmountCents: $financedCents,
            interestAmountCents: $interestCents,
            totalAmountCents: $totalCents,
            // The "typical" installment for display - the schedule's actual
            // final row may differ by a few cents; see installmentBreakdown.
            installmentAmountCents: intdiv($totalCents, $numberOfInstallments),
            installmentBreakdown: $breakdown,
        );
    }
}