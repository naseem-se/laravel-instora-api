<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;

class InvoiceService
{
    public function createForSale(Sale $sale, User $actor): Invoice
    {
        $invoice = new Invoice([
            'invoice_date' => now()->toDateString(),
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'additional_charges' => $sale->additional_charges,
            'tax' => $sale->tax,
            'interest' => '0.00',
            'late_fee' => '0.00',
            'total_amount' => $sale->total_amount,
            'paid_amount' => '0.00',
            'balance_amount' => $sale->total_amount,
            'status' => InvoiceStatus::Issued,
        ]);
        $invoice->company_id = $sale->company_id;
        $invoice->customer_id = $sale->customer_id;
        $invoice->sale_id = $sale->id;
        $invoice->invoice_number = $this->nextInvoiceNumber($sale->company_id);
        $invoice->created_by = $actor->id;
        $invoice->paid_amount = '0.00';
        $invoice->balance_amount = $sale->total_amount;
        $invoice->status = InvoiceStatus::Issued;
        $invoice->save();

        foreach ($sale->items as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'description' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => 0,
                'tax' => 0,
                'total' => $item->line_total,
            ]);
        }

        foreach ([['Additional charges', $sale->additional_charges], ['Tax', $sale->tax]] as [$description, $amount]) {
            if (Money::toCents($amount) <= 0) continue;
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => '1.000',
                'unit_price' => $amount,
                'discount' => 0,
                'tax' => 0,
                'total' => $amount,
            ]);
        }
        if (Money::toCents($sale->discount) > 0) {
            $discount = Money::fromCents(-Money::toCents($sale->discount));
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Sale discount',
                'quantity' => '1.000',
                'unit_price' => $discount,
                'discount' => $sale->discount,
                'tax' => 0,
                'total' => $discount,
            ]);
        }

        return $invoice->load('items');
    }

    public function createForPlan(InstallmentPlan $plan, User $actor): Invoice
    {
        $sale = Sale::query()->with('items')->where('installment_plan_id', $plan->id)->first();
        $invoiceTotal = Money::toCents($plan->total_amount) + ($sale ? Money::toCents($sale->down_payment) : 0);
        $invoice = new Invoice([
            'invoice_date' => now()->toDateString(),
            'subtotal' => $sale?->subtotal ?? $plan->financed_amount,
            'discount' => $sale?->discount ?? '0.00',
            'additional_charges' => $sale?->additional_charges ?? '0.00',
            'tax' => $sale?->tax ?? '0.00',
            'interest' => $plan->interest_amount,
            'late_fee' => '0.00',
            'total_amount' => Money::fromCents($invoiceTotal),
            'paid_amount' => '0.00',
            'balance_amount' => Money::fromCents($invoiceTotal),
            'status' => InvoiceStatus::Issued,
        ]);
        $invoice->company_id = $plan->company_id;
        $invoice->customer_id = $plan->customer_id;
        $invoice->installment_plan_id = $plan->id;
        $invoice->sale_id = $sale?->id;
        $invoice->invoice_number = $this->nextInvoiceNumber($plan->company_id);
        $invoice->created_by = $actor->id;
        $invoice->paid_amount = '0.00';
        $invoice->balance_amount = Money::fromCents($invoiceTotal);
        $invoice->status = InvoiceStatus::Issued;
        $invoice->save();

        if ($sale) {
            foreach ($sale->items as $saleItem) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $saleItem->product_id,
                    'description' => $saleItem->product_name,
                    'quantity' => $saleItem->quantity,
                    'unit_price' => $saleItem->unit_price,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $saleItem->line_total,
                ]);
            }
            if (Money::toCents($sale->additional_charges) > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Additional charges',
                    'quantity' => '1.000',
                    'unit_price' => $sale->additional_charges,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $sale->additional_charges,
                ]);
            }
            if (Money::toCents($sale->tax) > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Tax',
                    'quantity' => '1.000',
                    'unit_price' => $sale->tax,
                    'discount' => 0,
                    'tax' => $sale->tax,
                    'total' => $sale->tax,
                ]);
            }
            if (Money::toCents($sale->discount) > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Sale discount',
                    'quantity' => '1.000',
                    'unit_price' => Money::fromCents(-Money::toCents($sale->discount)),
                    'discount' => $sale->discount,
                    'tax' => 0,
                    'total' => Money::fromCents(-Money::toCents($sale->discount)),
                ]);
            }
            if (Money::toCents($plan->interest_amount) > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Financing charge',
                    'quantity' => '1.000',
                    'unit_price' => $plan->interest_amount,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $plan->interest_amount,
                ]);
            }
        } else {
            $this->createLineItems($invoice, $plan);
        }

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