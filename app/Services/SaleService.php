<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InstallmentPlanStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InstallmentPlanService $plans,
        private readonly InvoiceService $invoices,
        private readonly PaymentReversalService $reversals,
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
        private readonly PaymentService $payments,
        private readonly CustomerService $customers,
    ) {}

    public function create(array $data, CompanyContext $context, User $actor): Sale
    {
        $companyId = $context->requireCompanyId();

        return DB::transaction(function () use ($data, $companyId, $actor, $context) {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $customer = ! empty($data['customer_id'])
                ? Customer::query()->where('company_id', $companyId)->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail()
                : $this->customers->create($data['new_customer'], $context, $actor);
            $product = Product::query()->where('company_id', $companyId)->whereKey($data['product_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();

            $quantity = (int) $data['quantity'];
            $unitPriceCents = Money::toCents($product->cash_price);
            $subtotalCents = $unitPriceCents * $quantity;
            $discountCents = Money::toCents($data['discount'] ?? 0);
            $chargesCents = Money::toCents($data['additional_charges'] ?? 0);
            $taxCents = Money::toCents($data['tax'] ?? 0);
            if ($discountCents > $subtotalCents) {
                throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the sale subtotal.']);
            }

            $totalCents = $subtotalCents - $discountCents + $chargesCents + $taxCents;
            $downPaymentCents = Money::toCents($data['down_payment'] ?? 0);
            if ($totalCents <= 0 || $downPaymentCents >= $totalCents && $data['payment_type'] === 'installment') {
                throw ValidationException::withMessages(['down_payment' => 'The installment sale must have a positive balance after the down payment.']);
            }

            $sale = new Sale([
                'customer_id' => $customer->id,
                'payment_type' => $data['payment_type'],
                'status' => $data['payment_type'] === 'cash' ? 'completed' : 'pending',
                'subtotal' => Money::fromCents($subtotalCents),
                'discount' => Money::fromCents($discountCents),
                'additional_charges' => Money::fromCents($chargesCents),
                'tax' => Money::fromCents($taxCents),
                'total_amount' => Money::fromCents($totalCents),
                'down_payment' => Money::fromCents($data['payment_type'] === 'cash' ? $totalCents : $downPaymentCents),
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'receipt_disk' => $data['receipt_disk'] ?? null,
                'sold_at' => $data['payment_type'] === 'cash' ? now() : null,
            ]);
            $sale->company_id = $companyId;
            $sale->sale_number = $this->nextSaleNumber($companyId);
            $sale->created_by = $actor->id;
            $sale->save();

            $saleItemData = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price' => Money::fromCents($unitPriceCents),
                'line_total' => Money::fromCents($subtotalCents),
                'warehouse_id' => $data['warehouse_id'] ?? null,
            ];

            if ($product->warranty_days > 0) {
                $saleItemData['warranty_ends_at'] = now()->addDays($product->warranty_days);
            }
            if ($product->guarantee_days > 0) {
                $saleItemData['guarantee_ends_at'] = now()->addDays($product->guarantee_days);
            }
            if (!empty($data['product_item_id'])) {
                $saleItemData['product_item_id'] = $data['product_item_id'];
                \App\Models\ProductItem::where('id', $data['product_item_id'])->update(['status' => 'sold']);
            }

            $saleItem = $sale->items()->create($saleItemData);
            $this->changeStock($sale, $saleItem, $product, $quantity, 'out', 'Sale '.$sale->sale_number);

            if ($sale->payment_type === 'cash') {
                $invoice = $this->invoices->createForSale($sale->load('items'), $actor);
                $sale->invoice_id = $invoice->id;
                $sale->save();
                $this->ledger->record(
                    companyId: $companyId,
                    customerId: $customer->id,
                    transactionType: 'sale',
                    referenceType: 'sale',
                    referenceId: $sale->id,
                    debitCents: $totalCents,
                    creditCents: 0,
                    description: "Cash sale {$sale->sale_number}",
                    createdBy: $actor->id,
                );
                $this->payments->recordSalePayment(
                    $sale,
                    $invoice,
                    $totalCents,
                    PaymentMethod::from($data['payment_method']),
                    $actor,
                    "Payment for cash sale {$sale->sale_number}",
                );
            } else {
                $planData = array_merge($data['installment'], [
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                    'principal_amount' => Money::fromCents($totalCents),
                    'down_payment' => Money::fromCents($downPaymentCents),
                    'notes' => $data['notes'] ?? null,
                ]);
                $plan = $this->plans->create($planData, $context, $actor);
                $sale->installment_plan_id = $plan->id;
                $sale->save();
            }

            $this->audit->log(
                AuditAction::SaleCreated->value,
                entity: $sale,
                newValues: ['sale_number' => $sale->sale_number, 'payment_type' => $sale->payment_type, 'total_amount' => $sale->total_amount],
                companyId: $companyId,
                userId: $actor->id,
            );

            return $sale->load(['customer', 'items', 'installmentPlan', 'invoice.items']);
        });
    }

    public function cancel(Sale $sale, string $reason, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $actor) {
            Company::query()->whereKey($sale->company_id)->lockForUpdate()->firstOrFail();
            /** @var Sale $sale */
            $sale = Sale::query()->with(['installmentPlan', 'invoice', 'items', 'payments'])->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if (! in_array($sale->status, ['pending', 'completed'], true)) {
                throw new BusinessException('This sale can no longer be cancelled.', 'SALE_NOT_CANCELLABLE');
            }

            if ($sale->payment_type === 'installment' && $sale->installmentPlan) {
                if (in_array($sale->installmentPlan->status, [InstallmentPlanStatus::Completed, InstallmentPlanStatus::Settled], true)) {
                    throw new BusinessException('A completed or settled installment sale cannot be cancelled.', 'INSTALLMENT_SALE_NOT_CANCELLABLE');
                }
                $wasApproved = in_array($sale->installmentPlan->status, [InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue], true);
                $payments = Payment::query()
                    ->where('company_id', $sale->company_id)
                    ->where('status', 'completed')
                    ->where(function ($query) use ($sale) {
                        $query->where('sale_id', $sale->id)
                            ->orWhere('installment_plan_id', $sale->installment_plan_id);
                    })
                    ->lockForUpdate()
                    ->get();
                foreach ($payments as $payment) {
                    $this->reversals->reverse($payment, $actor, 'Sale cancellation: '.$reason);
                }
                $plan = InstallmentPlan::query()->whereKey($sale->installment_plan_id)->first();
                if ($plan && in_array($plan->status, [InstallmentPlanStatus::Pending, InstallmentPlanStatus::Active, InstallmentPlanStatus::Overdue], true)) {
                    $this->plans->cancel($plan, $actor, $reason);
                }
                if ($wasApproved && $plan) {
                    $this->ledger->record(
                        companyId: $sale->company_id,
                        customerId: $sale->customer_id,
                        transactionType: 'sale_cancellation',
                        referenceType: 'sale',
                        referenceId: $sale->id,
                        debitCents: 0,
                        creditCents: Money::toCents($sale->total_amount) + Money::toCents($plan->interest_amount),
                        description: "Cancellation of installment sale {$sale->sale_number}: {$reason}",
                        createdBy: $actor->id,
                        installmentPlanId: $plan->id,
                    );
                }
            } else {
                foreach ($sale->payments()->where('status', 'completed')->get() as $payment) {
                    $this->reversals->reverse($payment, $actor, $reason);
                }
                if ($sale->invoice) {
                    $sale->invoice->status = InvoiceStatus::Cancelled;
                    $sale->invoice->save();
                }
                $this->ledger->record(
                    companyId: $sale->company_id,
                    customerId: $sale->customer_id,
                    transactionType: 'sale_cancellation',
                    referenceType: 'sale',
                    referenceId: $sale->id,
                    debitCents: 0,
                    creditCents: Money::toCents($sale->total_amount),
                    description: "Cancellation of sale {$sale->sale_number}: {$reason}",
                    createdBy: $actor->id,
                );
            }

            $this->restoreAllItems($sale, $actor, 'Sale cancellation '.$sale->sale_number);
            $this->recordReturn($sale, 'cancellation', $reason, $actor, $sale->total_amount);
            $sale->status = 'cancelled';
            $sale->save();
            $this->audit->log(
                AuditAction::SaleCancelled->value,
                entity: $sale,
                newValues: ['status' => 'cancelled', 'reason' => $reason],
                companyId: $sale->company_id,
                userId: $actor->id,
            );

            return $sale->load(['customer', 'items', 'returns.items', 'invoice.items', 'installmentPlan']);
        });
    }

    public function return(Sale $sale, string $reason, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $actor) {
            Company::query()->whereKey($sale->company_id)->lockForUpdate()->firstOrFail();
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->status !== 'completed') {
                throw new BusinessException('Only completed sales can be returned.', 'SALE_NOT_RETURNABLE');
            }
            if ($sale->payment_type !== 'cash') {
                throw new BusinessException('Installment sales must be cancelled or settled through their installment plan.', 'INSTALLMENT_SALE_RETURN_REQUIRES_PLAN');
            }

            foreach ($sale->payments()->where('status', 'completed')->get() as $payment) {
                $this->reversals->reverse($payment, $actor, 'Sale return: '.$reason);
            }
            if ($sale->invoice) {
                $sale->invoice->status = InvoiceStatus::Cancelled;
                $sale->invoice->save();
            }
            $this->ledger->record(
                companyId: $sale->company_id,
                customerId: $sale->customer_id,
                transactionType: 'sale_return',
                referenceType: 'sale',
                referenceId: $sale->id,
                debitCents: 0,
                creditCents: Money::toCents($sale->total_amount),
                description: "Return of sale {$sale->sale_number}: {$reason}",
                createdBy: $actor->id,
            );

            $this->restoreAllItems($sale, $actor, 'Sale return '.$sale->sale_number);
            $this->recordReturn($sale, 'return', $reason, $actor, $sale->total_amount);
            $sale->status = 'returned';
            $sale->save();

            return $sale->load(['customer', 'items', 'returns.items', 'invoice.items']);
        });
    }

    private function changeStock(Sale $sale, SaleItem $item, Product $product, int $quantity, string $direction, string $note, ?User $actor = null): void
    {
        if ($product->stock_quantity === null) {
            return;
        }

        $warehouseId = $item->warehouse_id;
        $query = InventoryMovement::query()
            ->where('company_id', $sale->company_id)
            ->where('product_id', $product->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->whereNull('warehouse_id'));
        $onHand = (float) $query->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0) as on_hand")->value('on_hand');

        if ($direction === 'out' && $onHand < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'There is not enough stock at the selected stock location.']);
        }

        InventoryMovement::create([
            'company_id' => $sale->company_id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'type' => $direction,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $product->cost_price,
            'note' => $note,
            'created_by' => $actor?->id ?? $sale->created_by,
        ]);

        $product->setAttribute('stock_quantity', (int) InventoryMovement::query()
            ->where('company_id', $sale->company_id)
            ->where('product_id', $product->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0) as on_hand")
            ->value('on_hand'));
        $product->save();
    }

    private function restoreAllItems(Sale $sale, User $actor, string $note): void
    {
        foreach ($sale->items as $item) {
            if (! $item->product_id) continue;
            $product = Product::withTrashed()->where('company_id', $sale->company_id)->whereKey($item->product_id)->lockForUpdate()->first();
            if ($product) $this->changeStock($sale, $item, $product, $item->quantity, 'in', $note, $actor);
            
            if ($item->product_item_id) {
                \App\Models\ProductItem::where('id', $item->product_item_id)->update(['status' => 'returned']);
            }

            $item->returned_quantity = $item->quantity;
            $item->save();
        }
    }

    private function recordReturn(Sale $sale, string $type, string $reason, User $actor, string $amount): SaleReturn
    {
        $return = SaleReturn::create([
            'company_id' => $sale->company_id,
            'sale_id' => $sale->id,
            'return_number' => $this->nextReturnNumber($sale->company_id),
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'processed_by' => $actor->id,
        ]);

        foreach ($sale->items as $item) {
            $return->items()->create([
                'sale_item_id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->line_total,
            ]);
        }

        if ($type === 'return') {
            $this->audit->log(
                AuditAction::SaleReturned->value,
                entity: $return,
                newValues: ['return_number' => $return->return_number, 'amount' => $amount],
                companyId: $sale->company_id,
                userId: $actor->id,
            );
        }

        return $return;
    }

    private function nextSaleNumber(int $companyId): string
    {
        return $this->nextNumber(Sale::withTrashed()->where('company_id', $companyId), 'SAL');
    }

    private function nextReturnNumber(int $companyId): string
    {
        return $this->nextNumber(SaleReturn::query()->where('company_id', $companyId), 'RET');
    }

    private function nextNumber($query, string $prefix): string
    {
        $yearPrefix = $prefix.'-'.now()->year.'-';
        $count = $query->where('created_at', '>=', now()->startOfYear())->count();
        return $yearPrefix.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}