<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ListCustomersRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerListResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(ListCustomersRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->where('company_id', $context->requireCompanyId());

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('cnic', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = $request->input('sort', '-created_at');
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy($column, $direction);

        $customers = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(CustomerListResource::collection($customers)->response()->getData(true));
    }

    public function store(StoreCustomerRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customers->create($request->validated(), $context, $request->user());

        return ApiResponse::success(new CustomerResource($customer), 'Customer created successfully.', 201);
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $id, $context);
        $this->authorize('view', $customer);

        return ApiResponse::success(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $id, $context);
        $this->authorize('update', $customer);

        $customer = $this->customers->update($customer, $request->validated(), $request->user());

        return ApiResponse::success(new CustomerResource($customer), 'Customer updated successfully.');
    }

    public function destroy(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $id, $context);
        $this->authorize('delete', $customer);

        $this->customers->delete($customer, $request->user());

        return ApiResponse::success(null, 'Customer deleted successfully.');
    }
}