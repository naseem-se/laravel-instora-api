<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\UpdateWhatsAppCompanyAccessRequest;
use App\Http\Requests\WhatsApp\UpdateWhatsAppUserAccessRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsAppAccess;
use App\Services\WhatsAppAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WhatsAppAccessController extends Controller
{
    public function __construct(private readonly WhatsAppAccessService $access) {}

    public function index(): JsonResponse
    {
        $companies = Company::orderBy('name')->get();
        $accessRows = WhatsAppAccess::whereNull('user_id')->get()->keyBy('company_id');

        $data = $companies->map(fn ($company) => [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_status' => $company->status->value,
            // Fail-closed default: no row means not enabled.
            'enabled' => (bool) ($accessRows[$company->id]->enabled ?? false),
        ]);

        return ApiResponse::success(['data' => $data->values()]);
    }

    public function updateCompanyAccess(UpdateWhatsAppCompanyAccessRequest $request, int $companyId): JsonResponse
    {
        Company::findOrFail($companyId);

        $access = $this->access->setCompanyAccess($companyId, $request->validated()['enabled'], $request->user());

        return ApiResponse::success(['company_id' => $companyId, 'enabled' => $access->enabled], 'Access updated successfully.');
    }

    public function indexUsers(int $companyId): JsonResponse
    {
        Company::findOrFail($companyId);

        $users = User::where('company_id', $companyId)->orderBy('name')->get();
        $overrides = WhatsAppAccess::where('company_id', $companyId)->whereNotNull('user_id')->get()->keyBy('user_id');

        $data = $users->map(fn ($user) => [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // null = no override, follows the company policy
            'override' => isset($overrides[$user->id]) ? (bool) $overrides[$user->id]->enabled : null,
        ]);

        return ApiResponse::success(['data' => $data->values()]);
    }

    public function updateUserAccess(UpdateWhatsAppUserAccessRequest $request, int $companyId, int $userId): JsonResponse
    {
        User::where('company_id', $companyId)->findOrFail($userId);

        $access = $this->access->setUserAccess($companyId, $userId, $request->validated()['enabled'] ?? null, $request->user());

        return ApiResponse::success(['user_id' => $userId, 'override' => $access?->enabled], 'Access updated successfully.');
    }
}