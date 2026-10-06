<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeInventory($request, 'inventory.view');
        $companyId = $context->requireCompanyId();
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->when($request->boolean('alerts'), fn (Builder $query) => $query->whereRaw(
                '(SELECT COALESCE(SUM(CASE WHEN direction = \'in\' THEN quantity ELSE -quantity END), 0) FROM inventory_movements WHERE inventory_movements.product_id = products.id'.
                ($warehouseId ? ' AND inventory_movements.warehouse_id = ?' : '').
                ') <= products.reorder_level',
                $warehouseId ? [$warehouseId] : [],
            ))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 100), 200));

        $ids = $products->getCollection()->pluck('id');
        $balanceQuery = InventoryMovement::query()
            ->select('product_id', 'warehouse_id')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as on_hand")
            ->where('company_id', $companyId)
            ->whereIn('product_id', $ids)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->groupBy('product_id');

        $rows = $products->getCollection()->map(function (Product $product) use ($balanceQuery) {
            $locations = $balanceQuery->get($product->id, collect())->map(fn ($row) => [
                'warehouse_id' => $row->warehouse_id,
                'quantity' => (float) $row->on_hand,
            ])->values();
            $total = (float) $locations->sum('quantity');

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'cost_price' => $product->cost_price,
                'cash_price' => $product->cash_price,
                'installment_price' => $product->installment_price,
                'reorder_level' => (float) $product->reorder_level,
                'on_hand' => $total,
                'stock_value' => round($total * (float) $product->cost_price, 2),
                'stock_status' => $total <= 0 ? 'out_of_stock' : ($total <= (float) $product->reorder_level ? 'low_stock' : 'in_stock'),
                'locations' => $locations,
            ];
        });

        $warehouses = InventoryWarehouse::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success([
            'products' => $rows,
            'warehouses' => $warehouses,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function storeWarehouse(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeInventory($request, 'inventory.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('inventory_warehouses', 'name')->where('company_id', $context->requireCompanyId())],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $warehouse = new InventoryWarehouse($data);
        $warehouse->company_id = $context->requireCompanyId();
        $warehouse->save();

        return ApiResponse::success($warehouse, 'Warehouse created successfully.', 201);
    }

    public function updateWarehouse(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeInventory($request, 'inventory.manage');
        $companyId = $context->requireCompanyId();
        $warehouse = InventoryWarehouse::query()->where('company_id', $companyId)->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('inventory_warehouses', 'name')->where('company_id', $companyId)->ignore($warehouse->id)],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $warehouse->fill($data);
        $warehouse->save();

        return ApiResponse::success($warehouse, 'Warehouse updated successfully.');
    }

    public function destroyWarehouse(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorizeInventory($request, 'inventory.manage');
        $warehouse = InventoryWarehouse::query()
            ->where('company_id', $context->requireCompanyId())
            ->findOrFail($id);

        if ($warehouse->movements()->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => 'This warehouse has stock history and cannot be deleted. Transfer or adjust its stock first.',
            ]);
        }

        $warehouse->delete();

        return ApiResponse::success(null, 'Warehouse deleted successfully.');
    }

    public function history(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeInventory($request, 'inventory.view');
        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'warehouse:id,name,branch_name'])
            ->where('company_id', $context->requireCompanyId())
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->latest('id')
            ->paginate(min($request->integer('per_page', 30), 100));

        return ApiResponse::success($movements);
    }

    public function storeMovement(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorizeInventory($request, 'inventory.manage');
        $companyId = $context->requireCompanyId();
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'type' => ['required', Rule::in(['in', 'out', 'adjustment', 'transfer'])],
            'warehouse_id' => ['nullable', 'required_if:type,transfer', 'integer', Rule::exists('inventory_warehouses', 'id')->where('company_id', $companyId)],
            'destination_warehouse_id' => ['required_if:type,transfer', 'nullable', 'integer', 'different:warehouse_id', Rule::exists('inventory_warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'integer', 'gt:0'],
            'adjustment_direction' => ['required_if:type,adjustment', 'nullable', Rule::in(['in', 'out'])],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'variant_label' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $movement = DB::transaction(function () use ($data, $companyId, $request) {
            $product = Product::query()->where('company_id', $companyId)->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $warehouseIds = array_filter([$data['warehouse_id'] ?? null, $data['destination_warehouse_id'] ?? null]);
            $warehouses = InventoryWarehouse::query()->where('company_id', $companyId)->whereIn('id', $warehouseIds)->lockForUpdate()->get()->keyBy('id');
            if ($warehouses->count() !== count($warehouseIds)) {
                throw ValidationException::withMessages(['warehouse_id' => 'One or more warehouses are unavailable.']);
            }

            $type = $data['type'];
            $direction = $type === 'adjustment' ? $data['adjustment_direction'] : ($type === 'transfer' ? 'out' : $type);
            $quantity = (float) $data['quantity'];
            $warehouseId = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;
            if ($direction === 'out' && $this->balance($companyId, $product->id, $warehouseId) < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient stock at the selected warehouse.']);
            }

            if (! empty($data['serial_number'])) {
                if ($quantity !== 1.0) {
                    throw ValidationException::withMessages(['quantity' => 'Serial or IMEI tracked movements must have a quantity of 1.']);
                }

                $lastSerialMovement = InventoryMovement::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $product->id)
                    ->where('serial_number', $data['serial_number'])
                    ->latest('id')
                    ->first();

                if ($direction === 'in' && $lastSerialMovement?->direction === 'in') {
                    throw ValidationException::withMessages(['serial_number' => 'This serial or IMEI is already in stock.']);
                }
                $lastWarehouseId = $lastSerialMovement?->warehouse_id !== null
                    ? (int) $lastSerialMovement->warehouse_id
                    : null;
                if ($direction === 'out' && ($lastSerialMovement?->direction !== 'in' || $lastWarehouseId !== $warehouseId)) {
                    throw ValidationException::withMessages(['serial_number' => 'This serial or IMEI is not in stock at the selected warehouse.']);
                }
            }

            $attributes = [
                'company_id' => $companyId,
                'product_id' => $product->id,
                'type' => $type === 'transfer' ? 'out' : $type,
                'quantity' => $quantity,
                'unit_cost' => $data['unit_cost'] ?? $product->cost_price,
                'serial_number' => $data['serial_number'] ?? null,
                'variant_label' => $data['variant_label'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ];

            if ($type === 'transfer') {
                $transferId = (string) Str::uuid();
                InventoryMovement::create(array_merge($attributes, [
                    'type' => 'transfer', 'warehouse_id' => $data['warehouse_id'], 'direction' => 'out', 'transfer_id' => $transferId,
                ]));
                $movement = InventoryMovement::create(array_merge($attributes, [
                    'type' => 'transfer', 'warehouse_id' => $data['destination_warehouse_id'], 'direction' => 'in', 'transfer_id' => $transferId,
                ]));
            } else {
                $movement = InventoryMovement::create($attributes + ['warehouse_id' => $data['warehouse_id'] ?? null, 'direction' => $direction]);
            }

            $product->stock_quantity = $this->companyBalance($companyId, $product->id);
            $product->save();

            return $movement->load(['product:id,name,sku', 'warehouse:id,name,branch_name']);
        });

        return ApiResponse::success($movement, 'Stock movement recorded.', 201);
    }

    private function balance(int $companyId, int $productId, ?int $warehouseId): float
    {
        return (float) InventoryMovement::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->when(
                $warehouseId !== null,
                fn ($query) => $query->where('warehouse_id', $warehouseId),
                fn ($query) => $query->whereNull('warehouse_id'),
            )
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0) as balance")
            ->value('balance');
    }

    private function companyBalance(int $companyId, int $productId): float
    {
        return (float) InventoryMovement::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0) as balance")
            ->value('balance');
    }

    private function authorizeInventory(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}