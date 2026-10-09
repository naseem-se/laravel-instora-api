<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly CustomerService $customers,
    ) {}

    public function create(array $data, CompanyContext $context, User $actor): Quotation
    {
        $companyId = $context->requireCompanyId();

        return DB::transaction(function () use ($data, $companyId, $actor, $context) {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $customer = ! empty($data['customer_id'])
                ? Customer::query()->where('company_id', $companyId)->whereKey($data['customer_id'])->firstOrFail()
                : $this->customers->create($data['new_customer'], $context, $actor);

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages(['items' => 'At least one item is required.']);
            }

            $subtotalCents = 0;
            $resolvedItems = [];

            foreach ($items as $item) {
                $product = Product::query()
                    ->where('company_id', $companyId)
                    ->whereKey($item['product_id'])
                    ->where('status', 'active')
                    ->firstOrFail();

                $qty = (int) $item['quantity'];
                $unitPriceCents = Money::toCents($item['unit_price'] ?? $product->cash_price);
                $lineTotalCents = $unitPriceCents * $qty;
                $subtotalCents += $lineTotalCents;

                $resolvedItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => Money::fromCents($unitPriceCents),
                    'line_total' => Money::fromCents($lineTotalCents),
                ];
            }

            $discountCents = Money::toCents($data['discount'] ?? 0);
            $taxCents = Money::toCents($data['tax'] ?? 0);
            $totalCents = $subtotalCents - $discountCents + $taxCents;

            if ($discountCents > $subtotalCents) {
                throw ValidationException::withMessages(['discount' => 'Discount cannot exceed subtotal.']);
            }

            $quotation = new Quotation([
                'customer_id' => $customer->id,
                'subtotal' => Money::fromCents($subtotalCents),
                'discount' => Money::fromCents($discountCents),
                'tax' => Money::fromCents($taxCents),
                'total_amount' => Money::fromCents($totalCents),
                'notes' => $data['notes'] ?? null,
                'expires_at' => $data['expires_at'] ?? now()->addDays(30),
                'status' => 'draft',
            ]);
            $quotation->company_id = $companyId;
            $quotation->quotation_number = $this->nextQuotationNumber($companyId);
            $quotation->created_by = $actor->id;
            $quotation->save();

            foreach ($resolvedItems as $item) {
                $quotation->items()->create($item);
            }

            return $quotation->load(['customer', 'items']);
        });
    }

    public function convertToSale(Quotation $quotation, array $saleData, CompanyContext $context, User $actor)
    {
        return DB::transaction(function () use ($quotation, $saleData, $context, $actor) {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status === 'expired' || $quotation->status === 'rejected') {
                throw ValidationException::withMessages(['quotation' => 'This quotation can no longer be converted.']);
            }

            // Build sale payload from first quotation item (single-product sale model)
            $firstItem = $quotation->items()->first();
            if (! $firstItem) {
                throw ValidationException::withMessages(['quotation' => 'Quotation has no items.']);
            }

            $payload = array_merge($saleData, [
                'customer_id' => $quotation->customer_id,
                'product_id' => $firstItem->product_id,
                'quantity' => $firstItem->quantity,
                'discount' => $quotation->discount,
                'tax' => $quotation->tax,
            ]);

            $sale = $this->sales->create($payload, $context, $actor);

            $quotation->status = 'accepted';
            $quotation->save();

            return $sale;
        });
    }

    public function updateStatus(Quotation $quotation, string $status): Quotation
    {
        $quotation->status = $status;
        $quotation->save();

        return $quotation;
    }

    private function nextQuotationNumber(int $companyId): string
    {
        $prefix = 'QUO-' . now()->year . '-';
        $count = Quotation::withTrashed()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', now()->startOfYear())
            ->count();

        return $prefix . str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
