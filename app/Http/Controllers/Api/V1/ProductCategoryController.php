<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCategory\ListProductCategoriesRequest;
use App\Http\Requests\ProductCategory\StoreProductCategoryRequest;
use App\Http\Requests\ProductCategory\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryService $categories) {}

    public function index(ListProductCategoriesRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        $query = ProductCategory::query()->where('company_id', $context->requireCompanyId());

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $categories = $query->orderBy('name')->paginate($request->integer('per_page', 20));

        return ApiResponse::success(ProductCategoryResource::collection($categories)->response()->getData(true));
    }

    public function store(StoreProductCategoryRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('create', ProductCategory::class);

        $category = $this->categories->create($request->validated(), $context);

        return ApiResponse::success(new ProductCategoryResource($category), 'Category created successfully.', 201);
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $category = $this->findOwned(ProductCategory::class, $id, $context);
        $this->authorize('view', $category);

        return ApiResponse::success(new ProductCategoryResource($category));
    }

    public function update(UpdateProductCategoryRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $category = $this->findOwned(ProductCategory::class, $id, $context);
        $this->authorize('update', $category);

        $category = $this->categories->update($category, $request->validated());

        return ApiResponse::success(new ProductCategoryResource($category), 'Category updated successfully.');
    }

    public function destroy(int $id, CompanyContext $context): JsonResponse
    {
        $category = $this->findOwned(ProductCategory::class, $id, $context);
        $this->authorize('delete', $category);

        $this->categories->delete($category);

        return ApiResponse::success(null, 'Category deleted successfully.');
    }
}