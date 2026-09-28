<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgingInstallmentResource;
use App\Services\ReportService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function dashboard(CompanyContext $context): JsonResponse
    {
        $this->authorize('reports.view');

        return ApiResponse::success($this->reports->dashboard($context->requireCompanyId()));
    }

    public function agingSummary(CompanyContext $context): JsonResponse
    {
        $this->authorize('reports.view');

        return ApiResponse::success(['buckets' => $this->reports->agingSummary($context->requireCompanyId())]);
    }

    public function agingInstallments(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('reports.view');

        $bucket = $request->validate(['bucket' => ['required', Rule::in(ReportService::BUCKETS)]])['bucket'];

        $installments = $this->reports->agingInstallments($context->requireCompanyId(), $bucket, $request->integer('per_page', 20));

        return ApiResponse::success(AgingInstallmentResource::collection($installments)->response()->getData(true));
    }

    public function paymentMethods(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('reports.view');

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        return ApiResponse::success([
            'from' => $from,
            'to' => $to,
            'breakdown' => $this->reports->paymentMethodBreakdown($context->requireCompanyId(), $from, $to),
        ]);
    }
}