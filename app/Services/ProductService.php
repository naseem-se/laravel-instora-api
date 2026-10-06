<?php

namespace App\Services;

use App\Enums\ActiveStatus;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function create(array $data, CompanyContext $context): Product
    {
        return DB::transaction(function () use ($data, $context) {
            $companyId = $context->requireCompanyId();
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $data['sku'] = filled($data['sku'] ?? null)
                ? $data['sku']
                : $this->generateSku($companyId, $data['name']);
            $product = new Product($data);
            $product->company_id = $companyId;
            $product->status = ActiveStatus::Active;
            $product->save();

            if ((float) ($data['stock_quantity'] ?? 0) > 0) {
                InventoryMovement::create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'warehouse_id' => null,
                    'type' => 'in',
                    'direction' => 'in',
                    'quantity' => $data['stock_quantity'],
                    'unit_cost' => $product->cost_price,
                    'note' => 'Opening stock',
                ]);
            }

            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            Company::query()->whereKey($product->company_id)->lockForUpdate()->firstOrFail();
            $oldName = $product->name;
            $product->fill(collect($data)->except(['status'])->toArray());

            if ($product->name !== $oldName && ! array_key_exists('sku', $data)) {
                $product->sku = $this->generateSku($product->company_id, $product->name, $product->id);
            }

            if (isset($data['status'])) {
                $product->status = $data['status'];
            }

            $product->save();

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function generateSku(int $companyId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '-'));
        $base = Str::limit($base !== '' ? $base : 'PRODUCT', 80, '');
        $candidate = 'SKU-'.$base;
        $suffix = 1;

        while (Product::withTrashed()
            ->where('company_id', $companyId)
            ->where('sku', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $candidate = 'SKU-'.$base.'-'.(++$suffix);
        }

        return $candidate;
    }

}