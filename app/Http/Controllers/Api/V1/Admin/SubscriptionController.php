<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\RecordSubscriptionPaymentRequest;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionInvoiceResource;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use App\Models\PlatformSubscription;
use App\Models\PlatformSubscriptionInvoice;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): JsonResponse
    {
        $companies = Company::with('subscription')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        $data = $companies->getCollection()->map(fn ($company) => [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_status' => $company->status->value,
            'subscription' => $company->subscription ? new SubscriptionResource($company->subscription) : null,
        ]);

        return ApiResponse::success([
            'data' => $data->values(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'from' => $companies->firstItem(),
                'to' => $companies->lastItem(),
                'total' => $companies->total(),
            ],
        ]);
    }

    public function store(StoreSubscriptionRequest $request, int $companyId): JsonResponse
    {
        if (PlatformSubscription::where('company_id', $companyId)->exists()) {
            return ApiResponse::error('This company already has a subscription configured.', 'SUBSCRIPTION_ALREADY_EXISTS', 409);
        }

        $subscription = $this->subscriptions->create($companyId, $request->validated(), $request->user());

        return ApiResponse::success(new SubscriptionResource($subscription), 'Subscription created successfully.', 201);
    }

    public function show(Request $request, int $companyId): JsonResponse
    {
        $subscription = PlatformSubscription::where('company_id', $companyId)->with('company')->firstOrFail();

        $invoices = $subscription->invoices()
            ->with('payments.recordedBy')
            ->orderByDesc('period_start')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($subscription),
            'invoices' => [
                'data' => $invoices->getCollection()->map(fn ($invoice) => [
                    ...(new SubscriptionInvoiceResource($invoice))->resolve(),
                    'payments' => SubscriptionPaymentResource::collection($invoice->payments)->resolve(),
                ])->values(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'from' => $invoices->firstItem(),
                    'to' => $invoices->lastItem(),
                    'total' => $invoices->total(),
                ],
            ],
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, int $companyId): JsonResponse
    {
        $subscription = PlatformSubscription::where('company_id', $companyId)->firstOrFail();
        $subscription = $this->subscriptions->update($subscription, $request->validated(), $request->user());

        return ApiResponse::success(new SubscriptionResource($subscription), 'Subscription updated successfully.');
    }

    public function recordPayment(RecordSubscriptionPaymentRequest $request, int $invoiceId): JsonResponse
    {
        $invoice = PlatformSubscriptionInvoice::findOrFail($invoiceId);
        $payment = $this->subscriptions->recordPayment($invoice, $request->validated(), $request->user());

        return ApiResponse::success(new SubscriptionPaymentResource($payment), 'Payment recorded successfully.', 201);
    }
}