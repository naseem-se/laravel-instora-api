<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerLedgerResource;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLedgerController extends Controller
{
    public function index(Request $request, int $customerId, CompanyContext $context): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $customerId, $context);
        $this->authorize('view', $customer);

        $query = $customer->ledgerEntries()->orderByDesc('id');

        if ($planId = $request->input('installment_plan_id')) {
            $query->where('installment_plan_id', $planId);
        }

        $entries = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(CustomerLedgerResource::collection($entries)->response()->getData(true));
    }
}