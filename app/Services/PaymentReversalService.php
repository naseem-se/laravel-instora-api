<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAlreadyReversedException;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Support\Money;
use App\Support\NotificationVariables;
use Illuminate\Support\Facades\DB;
use App\Enums\NotificationType; use App\Services\NotificationService;

class PaymentReversalService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function reverse(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Reversed) {
                throw new PaymentAlreadyReversedException();
            }

            $plan = $payment->installment_plan_id
                ? InstallmentPlan::where('id', $payment->installment_plan_id)->lockForUpdate()->first()
                : null;

            $invoice = $payment->invoice_id
                ? Invoice::where('id', $payment->invoice_id)->lockForUpdate()->first()
                : null;

            foreach ($payment->allocations()->get() as $allocation) {
                $installment = Installment::where('id', $allocation->installment_id)->lockForUpdate()->first();

                if (! $installment) {
                    continue;
                }

                $allocatedCents = Money::toCents($allocation->allocated_amount);
                $newPaidCents = max(Money::toCents($installment->paid_amount) - $allocatedCents, 0);
                $newRemainingCents = Money::toCents($installment->remaining_amount) + $allocatedCents;

                $installment->paid_amount = Money::fromCents($newPaidCents);
                $installment->remaining_amount = Money::fromCents($newRemainingCents);
                $installment->status = $newPaidCents <= 0 ? InstallmentStatus::Pending : InstallmentStatus::Partial;
                $installment->paid_at = $newPaidCents <= 0 ? null : $installment->paid_at;
                $installment->save();
            }

            $paymentAmountCents = Money::toCents($payment->amount);

            if ($plan) {
                $wasCompleted = $plan->status === InstallmentPlanStatus::Completed;

                $plan->paid_amount = Money::fromCents(max(Money::toCents($plan->paid_amount) - $paymentAmountCents, 0));
                $plan->remaining_amount = Money::fromCents(Money::toCents($plan->remaining_amount) + $paymentAmountCents);

                if ($wasCompleted) {
                    $plan->status = InstallmentPlanStatus::Active;
                }

                $plan->save();
            }

            if ($invoice) {
                $this->invoices->reversePayment($invoice, $paymentAmountCents);
            }

            $payment->status = PaymentStatus::Reversed;
            $payment->save();

            $reversal = new PaymentReversal(['reason' => $reason, 'reversed_at' => now()]);
            $reversal->company_id = $payment->company_id;
            $reversal->payment_id = $payment->id;
            $reversal->reversed_by = $actor->id;
            $reversal->save();

            $this->ledger->record(
                companyId: $payment->company_id,
                customerId: $payment->customer_id,
                transactionType: 'payment_reversal',
                referenceType: 'payment_reversal',
                referenceId: $reversal->id,
                debitCents: $paymentAmountCents,
                creditCents: 0,
                description: "Reversal of payment {$payment->payment_number}: {$reason}",
                createdBy: $actor->id,
                installmentPlanId: $plan?->id,
            );

            $this->notifications->sendViaWhatsAppOrEmail(
                companyId: $payment->company_id,
                customerId: $payment->customer_id,
                type: NotificationType::PaymentReversed,
                variables: $plan
                    ? NotificationVariables::forPayment($payment, $plan)
                    : [
                        'payment_amount' => Money::format($payment->company->currency, Money::fromCents($paymentAmountCents)),
                        'remaining_balance' => '',
                    ],
                referenceType: 'payment',
                referenceId: $payment->id,
            );

            $this->audit->log(
                AuditAction::PaymentReversed->value,
                entity: $payment,
                oldValues: ['status' => 'completed'],
                newValues: ['status' => 'reversed', 'reason' => $reason],
                companyId: $payment->company_id,
                userId: $actor->id,
            );

            return $payment->load(['allocations.installment', 'reversal.reversedBy']);
        });
    }
}