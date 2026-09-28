<?php

namespace App\Services;

use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentStatus;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;

class PaymentAllocationService
{
    public function allocate(Payment $payment, InstallmentPlan $plan): void
    {
        $remainingCents = Money::toCents($payment->amount);

        $installments = $plan->installments()
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial, InstallmentStatus::Overdue])
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $installment) {
            if ($remainingCents <= 0) {
                break;
            }

            $installmentRemainingCents = Money::toCents($installment->remaining_amount);

            if ($installmentRemainingCents <= 0) {
                continue;
            }

            $amountForInstallment = min($remainingCents, $installmentRemainingCents);

            [$lateFeeCents, $interestCents, $principalCents] = $this->splitWaterfall($installment, $amountForInstallment);

            $allocation = new PaymentAllocation([
                'principal_amount' => Money::fromCents($principalCents),
                'interest_amount' => Money::fromCents($interestCents),
                'late_fee_amount' => Money::fromCents($lateFeeCents),
                'allocated_amount' => Money::fromCents($amountForInstallment),
            ]);
            $allocation->payment_id = $payment->id;
            $allocation->installment_id = $installment->id;
            $allocation->save();

            $newPaidCents = Money::toCents($installment->paid_amount) + $amountForInstallment;
            $newRemainingCents = $installmentRemainingCents - $amountForInstallment;

            $installment->paid_amount = Money::fromCents($newPaidCents);
            $installment->remaining_amount = Money::fromCents($newRemainingCents);
            $installment->status = $newRemainingCents <= 0 ? InstallmentStatus::Paid : InstallmentStatus::Partial;

            if ($newRemainingCents <= 0) {
                $installment->paid_at = $payment->payment_date;
            }

            $installment->save();

            $remainingCents -= $amountForInstallment;
        }

        $paymentAmountCents = Money::toCents($payment->amount);
        $newPlanPaidCents = Money::toCents($plan->paid_amount) + $paymentAmountCents;
        $newPlanRemainingCents = Money::toCents($plan->remaining_amount) - $paymentAmountCents;

        $plan->setAttribute('paid_amount', Money::fromCents($newPlanPaidCents));
        $plan->setAttribute('remaining_amount', Money::fromCents($newPlanRemainingCents));

        if ($newPlanRemainingCents <= 0) {
            $plan->status = InstallmentPlanStatus::Completed;
        }

        $plan->save();
    }

    /**
     *
     * @return array{0:int,1:int,2:int} [lateFeeCents, interestCents, principalCents]
     */
    private function splitWaterfall(Installment $installment, int $amountCents): array
    {
        $paidSoFar = $installment->allocations()
            ->selectRaw('COALESCE(SUM(late_fee_amount), 0) as late_fee, COALESCE(SUM(interest_amount), 0) as interest, COALESCE(SUM(principal_amount), 0) as principal')
            ->first();

        $remainingLateFeeCents = max(Money::toCents($installment->late_fee_amount ?? '0.00') - Money::toCents((string) ($paidSoFar?->late_fee ?? '0')), 0);
        $remainingInterestCents = max(Money::toCents($installment->interest_amount ?? '0.00') - Money::toCents((string) ($paidSoFar?->interest ?? '0')), 0);
        $remainingPrincipalCents = max(Money::toCents($installment->principal_amount ?? '0.00') - Money::toCents((string) ($paidSoFar?->principal ?? '0')), 0);

        $lateFeePortion = min($amountCents, $remainingLateFeeCents);
        $amountCents -= $lateFeePortion;

        $interestPortion = min($amountCents, $remainingInterestCents);
        $amountCents -= $interestPortion;

        $principalPortion = min($amountCents, $remainingPrincipalCents);
        $amountCents -= $principalPortion;

        $principalPortion += $amountCents;

        return [$lateFeePortion, $interestPortion, $principalPortion];
    }
}