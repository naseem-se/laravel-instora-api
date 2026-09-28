<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class CompanyProfileController extends Controller
{
    public function __construct(private readonly CompanyService $companies) {}

    public function show(CompanyContext $context): JsonResponse
    {
        $company = Company::findOrFail($context->requireCompanyId());

        return ApiResponse::success(new CompanyResource($company));
    }

    public function update(UpdateCompanyProfileRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('companies.update');

        $company = Company::findOrFail($context->requireCompanyId());
        $company = $this->companies->update($company, $request->validated(), $request->user());

        return ApiResponse::success(new CompanyResource($company), 'Company profile updated successfully.');
    }
}