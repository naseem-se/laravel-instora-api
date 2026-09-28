<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Support\Money;

class InvoiceService
{
    public function createForPlan(InstallmentPlan $plan, User $actor): Invoice
    {
        $invoice = new Invoice([
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->company_id = $plan->company_id;
        $invoice->customer_id = $plan->customer_id;
        $invoice->installment_plan_id = $plan->id;
        $invoice->invoice_number = $this->nextInvoiceNumber($plan->company_id);
        $invoice->subtotal = $plan->financed_amount;
        $invoice->discount = '0.00';
        $invoice->tax = '0.00';
        $invoice->interest = $plan->interest_amount;
        $invoice->late_fee = '0.00';
        $invoice->total_amount = $plan->total_amount;
        $invoice->paid_amount = '0.00';
        $invoice->balance_amount = $plan->total_amount;
        $invoice->status = InvoiceStatus::Issued;
        $invoice->created_by = $actor->id;
        $invoice->save();

        $this->createLineItems($invoice, $plan);

        return $invoice;
    }

    public function applyPayment(Invoice $invoice, int $amountCents): void
    {
        $newPaidCents = Money::toCents($invoice->paid_amount) + $amountCents;
        $newBalanceCents = Money::toCents($invoice->balance_amount) - $amountCents;

        $invoice->paid_amount = Money::fromCents($newPaidCents);
        $invoice->balance_amount = Money::fromCents($newBalanceCents);
        $invoice->status = match (true) {
            $newBalanceCents <= 0 => InvoiceStatus::Paid,
            $newPaidCents > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Issued,
        };
        $invoice->save();
    }

    public function reversePayment(Invoice $invoice, int $amountCents): void
    {
        $newPaidCents = max(Money::toCents($invoice->paid_amount) - $amountCents, 0);
        $newBalanceCents = Money::toCents($invoice->balance_amount) + $amountCents;

        $invoice->paid_amount = Money::fromCents($newPaidCents);
        $invoice->balance_amount = Money::fromCents($newBalanceCents);
        $invoice->status = $newPaidCents <= 0 ? InvoiceStatus::Issued : InvoiceStatus::Partial;
        $invoice->save();
    }

    /**
     * $deltaCents may be negative (a late fee waiver reversing a prior
     * charge). Called from LateFeeService, inside its own transaction.
     */
    public function applyLateFee(Invoice $invoice, int $deltaCents): void
    {
        $invoice->late_fee = Money::fromCents(Money::toCents($invoice->late_fee) + $deltaCents);
        $invoice->total_amount = Money::fromCents(Money::toCents($invoice->total_amount) + $deltaCents);
        $invoice->balance_amount = Money::fromCents(Money::toCents($invoice->balance_amount) + $deltaCents);
        $invoice->save();
    }

    private function createLineItems(Invoice $invoice, InstallmentPlan $plan): void
    {
        $items = [
            [
                'description' => "Installment plan {$plan->plan_number} \u{2014} financed principal",
                'quantity' => '1.000',
                'unit_price' => $plan->financed_amount,
                'total' => $plan->financed_amount,
            ],
        ];

        if (Money::toCents($plan->interest_amount) > 0) {
            $items[] = [
                'description' => 'Financing charge',
                'quantity' => '1.000',
                'unit_price' => $plan->interest_amount,
                'total' => $plan->interest_amount,
            ];
        }

        foreach ($items as $item) {
            $invoiceItem = new InvoiceItem($item);
            $invoiceItem->invoice_id = $invoice->id;
            $invoiceItem->discount = '0.00';
            $invoiceItem->tax = '0.00';
            $invoiceItem->save();
        }
    }

    private function nextInvoiceNumber(int $companyId): string
    {
        $prefix = 'INV-'.now()->year.'-';

        $count = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}