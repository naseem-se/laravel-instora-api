<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallmentPlan\CancelInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlan\ListInstallmentPlansRequest;
use App\Http\Requests\InstallmentPlan\StoreInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlan\StoreSettlementRequest;
use App\Http\Resources\InstallmentPlanListResource;
use App\Http\Resources\InstallmentPlanResource;
use App\Http\Resources\SettlementResource;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Models\InstallmentPlan;
use App\Services\InstallmentPlanService;
use App\Services\SaleService;
use App\Services\SettlementService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InstallmentPlanController extends Controller
{
    public function __construct(
        private readonly InstallmentPlanService $plans,
        private readonly SettlementService $settlements,
        private readonly SaleService $sales,
    ) {}

    public function index(ListInstallmentPlansRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('viewAny', InstallmentPlan::class);

        $query = InstallmentPlan::query()->with(['customer', 'product'])->where('company_id', $context->requireCompanyId());

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('plan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $query->orderByDesc('created_at');

        $plans = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(InstallmentPlanListResource::collection($plans)->response()->getData(true));
    }

    public function store(StoreInstallmentPlanRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('create', InstallmentPlan::class);

        $plan = $this->plans->create($request->validated(), $context, $request->user());

        return ApiResponse::success(
            new InstallmentPlanResource($plan->load(['customer', 'product', 'installments'])),
            'Installment plan created successfully.',
            201
        );
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('view', $plan);

        return ApiResponse::success(new InstallmentPlanResource(
            $plan->load(['customer', 'product', 'installments', 'createdBy', 'approvedBy', 'invoice.items', 'settlements.approvedBy'])
        ));
    }

    public function destroy(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('delete', $plan);
        $this->plans->delete($plan, $request->user());

        return ApiResponse::success(null, 'Completed installment plan deleted successfully.');
    }

    public function approve(Request $request, int $id, CompanyContext $context): JsonResponse
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('approve', $plan);

        $plan = $this->plans->approve($plan, $request->user());

        return ApiResponse::success(
            new InstallmentPlanResource($plan->load('invoice.items')),
            'Installment plan approved successfully.'
        );
    }

    public function cancel(CancelInstallmentPlanRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('cancel', $plan);

        $sale = Sale::query()->where('installment_plan_id', $plan->id)->first();
        if ($sale) {
            $sale = $this->sales->cancel($sale, $request->validated()['reason'] ?? 'Cancelled from installment plan', $request->user());

            return ApiResponse::success(new SaleResource($sale), 'Installment sale cancelled successfully.');
        }

        $plan = $this->plans->cancel($plan, $request->user(), $request->validated()['reason'] ?? null);

        return ApiResponse::success(new InstallmentPlanResource($plan), 'Installment plan cancelled successfully.');
    }

    public function settle(StoreSettlementRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('settle', $plan);

        $settlement = $this->settlements->settle($plan, $request->validated(), $request->user());

        return ApiResponse::success(new SettlementResource($settlement), 'Installment plan settled successfully.');
    }

    public function invoicePdf(int $id, CompanyContext $context): Response
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('view', $plan);

        $invoice = $plan->invoice()->with('items')->first();

        abort_if(! $invoice, 404, 'No invoice exists for this plan yet.');

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'plan' => $plan,
            'customer' => $plan->customer,
            'company' => $plan->company,
        ]);

        return $pdf->stream("{$invoice->invoice_number}.pdf");
    }

    public function statementPdf(int $id, CompanyContext $context): Response
    {
        $plan = $this->findOwned(InstallmentPlan::class, $id, $context);
        $this->authorize('view', $plan);

        $plan->load(['customer', 'company', 'installments', 'payments', 'invoice.items']);

        $pdf = Pdf::loadView('pdf.statement', [
            'plan' => $plan,
            'customer' => $plan->customer,
            'company' => $plan->company,
            'invoice' => $plan->invoice,
            'installments' => $plan->installments,
            'payments' => $plan->payments,
            'currency' => $plan->company->currency,
        ]);

        return $pdf->stream("statement_{$plan->plan_number}.pdf");
    }
}