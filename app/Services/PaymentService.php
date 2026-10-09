<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InstallmentPlanStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\OverpaymentNotAllowedException;
use App\Exceptions\InvoiceNotPayableException;
use App\Exceptions\PlanNotPayableException;
use App\Models\Company;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\Money;
use App\Support\NotificationVariables;
use Illuminate\Support\Facades\DB;
use App\Enums\NotificationType; use App\Services\NotificationService;

class PaymentService
{
    public function __construct(
        private readonly PaymentAllocationService $allocations,
        private readonly LedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data, CompanyContext $context, User $actor): Payment
    {
        $companyId = $context->requireCompanyId();

        return DB::transaction(function () use ($data, $companyId, $actor) {
            Company::where('id', $companyId)->lockForUpdate()->first();

            $plan = InstallmentPlan::where('company_id', $companyId)
                ->where('id', $data['installment_plan_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($plan->status, [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue], true)) {
                throw new PlanNotPayableException();
            }

            $amountCents = Money::toCents($data['amount']);
            $planRemainingCents = Money::toCents($plan->remaining_amount);

            if ($amountCents > $planRemainingCents) {
                throw new OverpaymentNotAllowedException();
            }

            $invoice = $plan->invoice()->first();

            if (! $invoice || ! in_array($invoice->status->value, ['issued', 'partial', 'overdue'], true)) {
                throw new InvoiceNotPayableException('An issued unpaid invoice is required before recording a payment.');
            }

            $invoiceBalanceCents = Money::toCents($invoice->balance_amount);
            if ($amountCents > $invoiceBalanceCents) {
                throw new OverpaymentNotAllowedException();
            }

            $payment = new Payment([
                'payment_date' => $data['payment_date'] ?? now(),
                'amount' => Money::fromCents($amountCents),
                'payment_method' => $data['payment_method'],
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'receipt_disk' => $data['receipt_disk'] ?? null,
            ]);
            $payment->company_id = $companyId;
            $payment->payment_number = $this->nextPaymentNumber($companyId);
            $payment->customer_id = $plan->customer_id;
            $payment->installment_plan_id = $plan->id;
            $payment->invoice_id = $invoice?->id;
            $payment->sale_id = $invoice?->sale_id;
            $payment->received_by = $actor->id;
            $payment->status = PaymentStatus::Completed;
            $payment->save();

            // Mutates and saves $plan and its installments in place.
            $this->allocations->allocate($payment, $plan);

            if ($invoice) {
                $this->invoices->applyPayment($invoice, $amountCents);
            }

            $this->ledger->record(
                companyId: $companyId,
                customerId: $plan->customer_id,
                transactionType: 'payment',
                referenceType: 'payment',
                referenceId: $payment->id,
                debitCents: 0,
                creditCents: $amountCents,
                description: "Payment {$payment->payment_number} for plan {$plan->plan_number}",
                createdBy: $actor->id,
                transactionDate: $payment->payment_date,
                installmentPlanId: $plan->id,
            );

            $this->notifications->sendViaWhatsAppOrEmail(
                companyId: $companyId,
                customerId: $plan->customer_id,
                type: NotificationType::PaymentReceived,
                variables: NotificationVariables::forPayment($payment, $plan, [
                    'invoice_number' => $invoice?->invoice_number ?? '',
                ]),
                referenceType: 'payment',
                referenceId: $payment->id,
            );

            $this->audit->log(
                AuditAction::PaymentCreated->value,
                entity: $payment,
                newValues: [
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'installment_plan_id' => $plan->id,
                ],
                companyId: $companyId,
                userId: $actor->id,
            );

            return $payment->load('allocations.installment');
        });
    }

    public function recordSalePayment(
        Sale $sale,
        Invoice $invoice,
        int $amountCents,
        PaymentMethod $method,
        User $actor,
        string $description,
    ): Payment {
        $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        $outstandingCents = max(
            Money::toCents($invoice->total_amount) - Money::toCents($invoice->paid_amount),
            0,
        );
        if (Money::toCents($invoice->balance_amount) !== $outstandingCents) {
            $invoice->balance_amount = Money::fromCents($outstandingCents);
            $invoice->save();
        }

        if ($amountCents <= 0 || $amountCents > $outstandingCents) {
            throw new OverpaymentNotAllowedException();
        }

        $payment = new Payment([
            'payment_date' => now(),
            'amount' => Money::fromCents($amountCents),
            'payment_method' => $method,
        ]);
        $payment->company_id = $sale->company_id;
        $payment->customer_id = $sale->customer_id;
        $payment->sale_id = $sale->id;
        $payment->invoice_id = $invoice->id;
        $payment->payment_number = $this->nextPaymentNumber($sale->company_id);
        $payment->received_by = $actor->id;
        $payment->status = PaymentStatus::Completed;
        $payment->save();

        $this->invoices->applyPayment($invoice, $amountCents);
        $this->ledger->record(
            companyId: $sale->company_id,
            customerId: $sale->customer_id,
            transactionType: 'sale_payment',
            referenceType: 'payment',
            referenceId: $payment->id,
            debitCents: 0,
            creditCents: $amountCents,
            description: $description,
            createdBy: $actor->id,
        );
        $this->audit->log(
            AuditAction::PaymentCreated->value,
            entity: $payment,
            newValues: ['payment_number' => $payment->payment_number, 'amount' => $payment->amount, 'sale_id' => $sale->id],
            companyId: $sale->company_id,
            userId: $actor->id,
        );

        return $payment;
    }

    private function nextPaymentNumber(int $companyId): string
    {
        $prefix = 'PAY-'.now()->year.'-';

        $count = Payment::where('company_id', $companyId)
            ->where('payment_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}