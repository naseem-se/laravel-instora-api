<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(CompanyContext $context): JsonResponse
    {
        $this->authorize('users.view');

        $roles = $context->isSuperAdmin()
            ? Role::whereNull('company_id')->pluck('name')
            : Role::where('company_id', $context->requireCompanyId())->pluck('name');

        return ApiResponse::success(['roles' => $roles->values()]);
    }
}