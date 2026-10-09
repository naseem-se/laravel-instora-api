<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductsRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(ListProductsRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->with('category')->where('company_id', $context->requireCompanyId());

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = $request->input('sort', '-created_at');
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy($column, $direction);

        $products = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(ProductResource::collection($products)->response()->getData(true));
    }

    public function store(StoreProductRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->products->create($request->validated(), $context);

        return ApiResponse::success(new ProductResource($product), 'Product created successfully.', 201);
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $product = $this->findOwned(Product::class, $id, $context);
        $this->authorize('view', $product);

        return ApiResponse::success(new ProductResource($product->load('category')));
    }

    public function update(UpdateProductRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $product = $this->findOwned(Product::class, $id, $context);
        $this->authorize('update', $product);

        $product = $this->products->update($product, $request->validated());

        return ApiResponse::success(new ProductResource($product->load('category')), 'Product updated successfully.');
    }

    public function destroy(int $id, CompanyContext $context): JsonResponse
    {
        $product = $this->findOwned(Product::class, $id, $context);
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return ApiResponse::success(null, 'Product deleted successfully.');
    }

    public function items(int $id, \Illuminate\Http\Request $request, CompanyContext $context): JsonResponse
    {
        $product = $this->findOwned(Product::class, $id, $context);
        $this->authorize('view', $product);

        $query = \App\Models\ProductItem::query()
            ->where('company_id', $context->requireCompanyId())
            ->where('product_id', $product->id)
            ->where('status', 'in_stock');
            
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($search = $request->input('search')) {
            $query->where('serial_number', 'like', "%{$search}%");
        }

        $items = $query->limit(50)->get();

        return ApiResponse::success(['data' => $items]);
    }
}