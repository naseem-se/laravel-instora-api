<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Services\UserService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

/**
 * Lets a Super Admin create the first (or an additional) user for a
 * specific company. This was a real gap: POST /api/v1/users always scopes
 * via the acting user's own company_id, which is null for Super Admin -
 * a newly created company had no way to get its first Company Admin.
 */
class CompanyUserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function store(StoreUserRequest $request, int $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        // A context built for the target company, not the acting Super
        // Admin's own (null) one - UserService::create() only needs
        // requireCompanyId() to resolve, which this satisfies.
        $context = new CompanyContext($company->id, isSuperAdmin: false);

        $user = $this->users->create($request->validated(), $context, $request->user());

        return ApiResponse::success(new UserResource($user), 'Company user created successfully.', 201);
    }
}