<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform administration. Every route here sits behind the
 * `super_admin` middleware applied at the route-group level.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companies) {}

    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(CompanyResource::collection($companies)->response()->getData(true));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->companies->create($request->validated(), $request->user());

        return ApiResponse::success(new CompanyResource($company), 'Company created successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new CompanyResource(Company::findOrFail($id)));
    }

    public function update(UpdateCompanyRequest $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company = $this->companies->update($company, $request->validated(), $request->user());

        return ApiResponse::success(new CompanyResource($company), 'Company updated successfully.');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->companies->delete($company, $request->user());

        return ApiResponse::success(null, 'Company and all associated records deleted successfully.');
    }
}