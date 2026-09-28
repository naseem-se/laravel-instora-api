<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\IdempotencyReplayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ListPaymentsRequest;
use App\Http\Requests\Payment\ReversePaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentListResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\IdempotencyService;
use App\Services\PaymentReversalService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentReversalService $reversals,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(ListPaymentsRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['customer', 'installmentPlan'])->where('company_id', $context->requireCompanyId());

        if ($planId = $request->input('installment_plan_id')) {
            $query->where('installment_plan_id', $planId);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('installmentPlan', fn ($plan) => $plan->where('plan_number', 'like', "%{$search}%"));
            });
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->input('payment_method')) {
            $query->where('payment_method', $method);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }

        if ($toDate = $request->input('to_date')) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        $query->orderByDesc('payment_date');

        $payments = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(PaymentListResource::collection($payments)->response()->getData(true));
    }

    public function store(StorePaymentRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $companyId = $context->requireCompanyId();
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $record = $this->idempotency->begin('payments.store', $companyId, $idempotencyKey, $request->validated());
        } catch (IdempotencyReplayException $e) {
            return response()->json($e->body, $e->status);
        }

        $payment = $this->payments->create($request->validated(), $context, $request->user());

        $response = ApiResponse::success(new PaymentResource($payment), 'Payment recorded successfully.', 201);

        if ($record) {
            $this->idempotency->complete($record, $response->status(), json_decode($response->getContent(), true));
        }

        return $response;
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $payment = $this->findOwned(Payment::class, $id, $context);
        $this->authorize('view', $payment);

        return ApiResponse::success(new PaymentResource(
            $payment->load(['allocations.installment', 'customer', 'installmentPlan', 'receivedBy', 'reversal.reversedBy'])
        ));
    }

    public function reverse(ReversePaymentRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $payment = $this->findOwned(Payment::class, $id, $context);
        $this->authorize('reverse', $payment);

        $payment = $this->reversals->reverse($payment, $request->user(), $request->validated()['reason']);

        return ApiResponse::success(new PaymentResource($payment), 'Payment reversed successfully.');
    }

    /** Same on-demand generation and JSON-envelope exception as invoicePdf() above. */
    public function receiptPdf(int $id, CompanyContext $context): Response
    {
        $payment = $this->findOwned(Payment::class, $id, $context);
        $this->authorize('view', $payment);

        $payment->load(['allocations.installment', 'customer', 'installmentPlan', 'receivedBy', 'reversal']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'company' => $payment->company,
        ]);

        return $pdf->stream("{$payment->payment_number}.pdf");
    }
}