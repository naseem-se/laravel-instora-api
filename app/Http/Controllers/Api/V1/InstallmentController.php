<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installment\WaiveLateFeeRequest;
use App\Http\Resources\InstallmentResource;
use App\Models\Installment;
use App\Services\LateFeeService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class InstallmentController extends Controller
{
    public function __construct(private readonly LateFeeService $lateFees) {}

    public function waiveLateFee(WaiveLateFeeRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorize('installments.update');

        $installment = $this->findOwned(Installment::class, $id, $context);
        $installment = $this->lateFees->waive($installment, $request->user(), $request->validated()['reason'] ?? null);

        return ApiResponse::success(new InstallmentResource($installment), 'Late fee waived successfully.');
    }
}