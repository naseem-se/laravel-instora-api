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

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(): JsonResponse
    {
        $companies = Company::with('subscription')->orderBy('name')->get();

        $data = $companies->map(fn ($company) => [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_status' => $company->status->value,
            'subscription' => $company->subscription ? new SubscriptionResource($company->subscription) : null,
        ]);

        return ApiResponse::success(['data' => $data->values()]);
    }

    public function store(StoreSubscriptionRequest $request, int $companyId): JsonResponse
    {
        if (PlatformSubscription::where('company_id', $companyId)->exists()) {
            return ApiResponse::error('This company already has a subscription configured.', 'SUBSCRIPTION_ALREADY_EXISTS', 409);
        }

        $subscription = $this->subscriptions->create($companyId, $request->validated(), $request->user());

        return ApiResponse::success(new SubscriptionResource($subscription), 'Subscription created successfully.', 201);
    }

    public function show(int $companyId): JsonResponse
    {
        $subscription = PlatformSubscription::where('company_id', $companyId)->with('company')->firstOrFail();

        $invoices = $subscription->invoices()->with('payments.recordedBy')->orderByDesc('period_start')->limit(24)->get();

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($subscription),
            'invoices' => $invoices->map(fn ($invoice) => [
                ...(new SubscriptionInvoiceResource($invoice))->resolve(),
                'payments' => SubscriptionPaymentResource::collection($invoice->payments)->resolve(),
            ]),
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