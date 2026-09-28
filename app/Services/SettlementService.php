<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Exceptions\InvalidSettlementAmountException;
use App\Exceptions\PlanNotSettleableException;
use App\Models\Invoice;
use App\Models\InstallmentPlan;
use App\Models\Settlement;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\Money;
use App\Support\NotificationVariables;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly LedgerService $ledger,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function settle(InstallmentPlan $plan, array $data, User $actor): Settlement
    {
        if (! in_array($plan->status, [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue], true)) {
            throw new PlanNotSettleableException();
        }

        return DB::transaction(function () use ($plan, $data, $actor) {
            $plan = InstallmentPlan::with('company')->where('id', $plan->id)->lockForUpdate()->firstOrFail();
            /** @var InstallmentPlan $plan */

            $outstandingCents = Money::toCents($plan->remaining_amount);

            if ($outstandingCents <= 0) {
                throw new PlanNotSettleableException('This plan has no outstanding balance to settle.');
            }

            $discountCents = Money::toCents($data['discount_amount'] ?? 0);
            $lateFeeWaivedCents = Money::toCents($data['late_fee_waived'] ?? 0);
            $finalCents = $outstandingCents - $discountCents - $lateFeeWaivedCents;

            if ($finalCents < 0) {
                throw new InvalidSettlementAmountException();
            }

            $payment = null;

            if ($finalCents > 0) {
                $payment = $this->payments->create([
                    'installment_plan_id' => $plan->id,
                    'amount' => Money::fromCents($finalCents),
                    'payment_method' => $data['payment_method'],
                    'payment_date' => $data['payment_date'] ?? now(),
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => "Settlement payment for plan {$plan->plan_number}",
                ], new CompanyContext($plan->company_id, false), $actor);

                $plan->refresh();
            }

            // Whatever is still owed after the settlement payment (if any)
            // is the amount being forgiven - discount plus waived late fee,
            // by construction of finalCents above.
            $waivedCents = Money::toCents($plan->remaining_amount);

            if ($waivedCents > 0) {
                $this->waiveRemainingInstallments($plan);
            }

            $plan->remaining_amount = '0.00';
            $plan->status = InstallmentPlanStatus::Settled;
            $plan->save();

            $invoice = $plan->invoice()->first();
            /** @var Invoice|null $invoice */
            if ($invoice) {
                $invoice->total_amount = Money::fromCents(Money::toCents($invoice->total_amount) - $waivedCents);
                $invoice->balance_amount = '0.00';
                $invoice->status = InvoiceStatus::Paid;
                $invoice->save();
            }

            $settlement = new Settlement([
                'settlement_date' => now()->toDateString(),
                'outstanding_amount' => Money::fromCents($outstandingCents),
                'discount_amount' => Money::fromCents($discountCents),
                'late_fee_waived' => Money::fromCents($lateFeeWaivedCents),
                'final_amount' => Money::fromCents($finalCents),
            ]);
            $settlement->company_id = $plan->company_id;
            $settlement->installment_plan_id = $plan->id;
            $settlement->customer_id = $plan->customer_id;
            $settlement->payment_id = $payment?->id;
            $settlement->approved_by = $actor->id;
            $settlement->save();

            if ($waivedCents > 0) {
                $this->ledger->record(
                    companyId: $plan->company_id,
                    customerId: $plan->customer_id,
                    transactionType: 'settlement',
                    referenceType: 'settlement',
                    referenceId: $settlement->id,
                    debitCents: 0,
                    creditCents: $waivedCents,
                    description: 'Settlement of plan '.$plan->plan_number.': '
                        .Money::format($plan->company->currency, Money::fromCents($discountCents)).' discount, '
                        .Money::format($plan->company->currency, Money::fromCents($lateFeeWaivedCents)).' late fee waived',
                    createdBy: $actor->id,
                    installmentPlanId: $plan->id,
                );
            }

            $this->notifications->send(
                companyId: $plan->company_id,
                customerId: $plan->customer_id,
                type: NotificationType::PlanSettled,
                channel: NotificationChannel::Email,
                variables: NotificationVariables::forPlan($plan),
                referenceType: 'installment_plan',
                referenceId: $plan->id,
            );

            $this->audit->log(
                AuditAction::InstallmentPlanSettled->value,
                entity: $plan,
                newValues: [
                    'outstanding_amount' => $settlement->outstanding_amount,
                    'discount_amount' => $settlement->discount_amount,
                    'late_fee_waived' => $settlement->late_fee_waived,
                    'final_amount' => $settlement->final_amount,
                ],
                companyId: $plan->company_id,
                userId: $actor->id,
            );

            return $settlement->load(['payment', 'approvedBy']);
        });
    }

    private function waiveRemainingInstallments(InstallmentPlan $plan): void
    {
        $plan->installments()
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial, InstallmentStatus::Overdue])
            ->lockForUpdate()
            ->get()
            ->each(function ($installment) {
                if (Money::toCents($installment->remaining_amount) <= 0) {
                    return;
                }

                $installment->remaining_amount = '0.00';
                $installment->status = InstallmentStatus::Waived;
                $installment->save();
            });
    }
}