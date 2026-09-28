<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InstallmentStatus;
use App\Enums\LateFeeType;
use App\Exceptions\NoLateFeeToWaiveException;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class LateFeeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Recomputes and applies late fees for every currently-overdue
     * installment. Idempotent by design: the target fee is recalculated
     * fresh each run and only ever applied as a delta if it increased -
     * running this twice on the same day, or restarting a failed job
     * partway through, never double-charges.
     */
    public function applyLateFees(): int
    {
        $applied = 0;

        Installment::with('installmentPlan.company')
            ->where('status', InstallmentStatus::Overdue)
            ->chunkById(200, function ($installments) use (&$applied) {
                foreach ($installments as $installment) {
                    /** @var Installment $installment */
                    if ($this->applyToInstallment($installment)) {
                        $applied++;
                    }
                }
            });

        return $applied;
    }

    private function applyToInstallment(Installment $installment): bool
    {
        $plan = $installment->installmentPlan;

        if (! $plan || $plan->late_fee_type === LateFeeType::None) {
            return false;
        }

        // due_date is guaranteed to be in the past for an Overdue
        // installment (see InstallmentOverdueStatusService), so this is
        // always a positive count.
        $daysOverdue = now()->startOfDay()->diffInDays($installment->due_date->copy()->startOfDay());

        $baseCents = Money::toCents($installment->scheduled_amount);

        $targetFeeCents = match ($plan->late_fee_type) {
            LateFeeType::Fixed => Money::toCents($plan->late_fee_amount ?? '0'),
            LateFeeType::Percentage => Money::percentageOf($baseCents, $plan->late_fee_rate ?? '0'),
            LateFeeType::PerDay => Money::toCents($plan->late_fee_amount ?? '0') * $daysOverdue,
            LateFeeType::PercentagePerDay => Money::percentageOf($baseCents, $plan->late_fee_rate ?? '0') * $daysOverdue,
            default => 0,
        };

        if ($plan->maximum_late_fee) {
            $targetFeeCents = min($targetFeeCents, Money::toCents($plan->maximum_late_fee));
        }

        $currentFeeCents = Money::toCents($installment->late_fee_amount);

        if ($targetFeeCents <= $currentFeeCents) {
            return false;
        }

        $deltaCents = $targetFeeCents - $currentFeeCents;

        DB::transaction(function () use ($installment, $plan, $targetFeeCents, $deltaCents) {
            $installment = Installment::where('id', $installment->id)->lockForUpdate()->firstOrFail();
            $lockedPlan = InstallmentPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();

            $installment->late_fee_amount = Money::fromCents($targetFeeCents);
            $installment->remaining_amount = Money::fromCents(Money::toCents($installment->remaining_amount) + $deltaCents);
            $installment->save();

            $lockedPlan->remaining_amount = Money::fromCents(Money::toCents($lockedPlan->remaining_amount) + $deltaCents);
            $lockedPlan->save();

            $invoice = $lockedPlan->invoice()->first();
            if ($invoice) {
                $this->invoices->applyLateFee($invoice, $deltaCents);
            }

            $this->ledger->record(
                companyId: $lockedPlan->company_id,
                customerId: $lockedPlan->customer_id,
                transactionType: 'late_fee',
                referenceType: 'installment',
                referenceId: $installment->id,
                debitCents: $deltaCents,
                creditCents: 0,
                description: "Late fee applied to installment #{$installment->installment_number} of plan {$lockedPlan->plan_number}",
            );
        });

        return true;
    }

    public function waive(Installment $installment, User $actor, ?string $reason = null): Installment
    {
        return DB::transaction(function () use ($installment, $actor, $reason) {
            $installment = Installment::where('id', $installment->id)->lockForUpdate()->firstOrFail();
            $plan = InstallmentPlan::where('id', $installment->installment_plan_id)->lockForUpdate()->firstOrFail();

            $paidLateFeeCents = Money::toCents((string) $installment->allocations()->sum('late_fee_amount'));
            $outstandingLateFeeCents = max(Money::toCents($installment->late_fee_amount) - $paidLateFeeCents, 0);

            if ($outstandingLateFeeCents <= 0) {
                throw new NoLateFeeToWaiveException();
            }

            $installment->late_fee_amount = Money::fromCents(Money::toCents($installment->late_fee_amount) - $outstandingLateFeeCents);
            $newRemainingCents = max(Money::toCents($installment->remaining_amount) - $outstandingLateFeeCents, 0);
            $installment->remaining_amount = Money::fromCents($newRemainingCents);

            // If this waiver brings the installment to zero, mark it
            // Waived rather than Paid - the balance was forgiven, not collected.
            if ($newRemainingCents <= 0) {
                $installment->status = InstallmentStatus::Waived;
            }
            $installment->save();

            $plan->remaining_amount = Money::fromCents(max(Money::toCents($plan->remaining_amount) - $outstandingLateFeeCents, 0));
            $plan->save();

            $invoice = $plan->invoice()->first();
            if ($invoice) {
                $this->invoices->applyLateFee($invoice, -$outstandingLateFeeCents);
            }

            $this->ledger->record(
                companyId: $plan->company_id,
                customerId: $plan->customer_id,
                transactionType: 'late_fee_waiver',
                referenceType: 'installment',
                referenceId: $installment->id,
                debitCents: 0,
                creditCents: $outstandingLateFeeCents,
                description: "Late fee waived on installment #{$installment->installment_number} of plan {$plan->plan_number}".($reason ? ": {$reason}" : ''),
                createdBy: $actor->id,
            );

            $this->audit->log(
                AuditAction::LateFeeWaived->value,
                entity: $installment,
                newValues: ['waived_amount' => Money::fromCents($outstandingLateFeeCents), 'reason' => $reason],
                companyId: $plan->company_id,
                userId: $actor->id,
            );

            return $installment;
        });
    }
}