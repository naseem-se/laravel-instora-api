<?php

namespace App\Services;

use App\Enums\ActiveStatus;
use App\Models\Product;
use App\Support\CompanyContext;

class ProductService
{
    public function create(array $data, CompanyContext $context): Product
    {
        $product = new Product($data);
        $product->company_id = $context->requireCompanyId();
        $product->status = ActiveStatus::Active;
        $product->save();

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $product->fill(collect($data)->except(['status'])->toArray());

        if (isset($data['status'])) {
            $product->status = $data['status'];
        }

        $product->save();

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}