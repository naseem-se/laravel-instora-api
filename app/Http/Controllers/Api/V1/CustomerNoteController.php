<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerNoteRequest;
use App\Http\Resources\CustomerNoteResource;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class CustomerNoteController extends Controller
{
    public function index(int $customerId, CompanyContext $context): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $customerId, $context);
        $this->authorize('view', $customer);

        $notes = $customer->notes()->with('user')->latest()->paginate(20);

        return ApiResponse::success(CustomerNoteResource::collection($notes)->response()->getData(true));
    }

    public function store(StoreCustomerNoteRequest $request, int $customerId, CompanyContext $context): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $customerId, $context);
        $this->authorize('update', $customer);

        // ->make() (not ->create()) because company_id and user_id are guarded -
        // see the CustomerService note on why guarded fields need direct assignment.
        $note = $customer->notes()->make(['note' => $request->validated()['note']]);
        $note->company_id = $customer->company_id;
        $note->user_id = $request->user()->id;
        $note->save();

        return ApiResponse::success(new CustomerNoteResource($note->load('user')), 'Note added successfully.', 201);
    }
}