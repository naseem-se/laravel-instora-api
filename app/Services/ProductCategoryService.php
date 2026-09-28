<?php

namespace App\Services;

use App\Enums\ActiveStatus;
use App\Models\ProductCategory;
use App\Support\CompanyContext;

class ProductCategoryService
{
    public function create(array $data, CompanyContext $context): ProductCategory
    {
        $category = new ProductCategory($data);
        $category->company_id = $context->requireCompanyId();
        $category->status = ActiveStatus::Active;
        $category->save();

        return $category;
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $category->fill(collect($data)->except(['status'])->toArray());

        if (isset($data['status'])) {
            $category->status = $data['status'];
        }

        $category->save();

        return $category;
    }

    public function delete(ProductCategory $category): void
    {
        $category->delete();
    }
}