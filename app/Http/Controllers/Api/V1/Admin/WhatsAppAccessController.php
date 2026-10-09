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
use Illuminate\Http\Request;

class WhatsAppAccessController extends Controller
{
    public function __construct(private readonly WhatsAppAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $companies = Company::orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));
        $companyIds = $companies->getCollection()->pluck('id');
        $accessRows = WhatsAppAccess::whereNull('user_id')
            ->whereIn('company_id', $companyIds)
            ->get()
            ->keyBy('company_id');

        $data = $companies->getCollection()->map(fn ($company) => [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_status' => $company->status->value,
            // Fail-closed default: no row means not enabled.
            'enabled' => (bool) ($accessRows[$company->id]->enabled ?? false),
        ]);

        return ApiResponse::success([
            'data' => $data->values(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'from' => $companies->firstItem(),
                'to' => $companies->lastItem(),
                'total' => $companies->total(),
            ],
        ]);
    }

    public function updateCompanyAccess(UpdateWhatsAppCompanyAccessRequest $request, int $companyId): JsonResponse
    {
        Company::findOrFail($companyId);

        $access = $this->access->setCompanyAccess($companyId, $request->validated()['enabled'], $request->user());

        return ApiResponse::success(['company_id' => $companyId, 'enabled' => $access->enabled], 'Access updated successfully.');
    }

    public function indexUsers(Request $request, int $companyId): JsonResponse
    {
        Company::findOrFail($companyId);

        $users = User::where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));
        $userIds = $users->getCollection()->pluck('id');
        $overrides = WhatsAppAccess::where('company_id', $companyId)
            ->whereNotNull('user_id')
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $data = $users->getCollection()->map(fn ($user) => [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // null = no override, follows the company policy
            'override' => isset($overrides[$user->id]) ? (bool) $overrides[$user->id]->enabled : null,
        ]);

        return ApiResponse::success([
            'data' => $data->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function updateUserAccess(UpdateWhatsAppUserAccessRequest $request, int $companyId, int $userId): JsonResponse
    {
        User::where('company_id', $companyId)->findOrFail($userId);

        $access = $this->access->setUserAccess($companyId, $userId, $request->validated()['enabled'] ?? null, $request->user());

        return ApiResponse::success(['user_id' => $userId, 'override' => $access?->enabled], 'Access updated successfully.');
    }
}