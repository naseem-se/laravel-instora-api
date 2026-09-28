<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Support\CompanyContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->company_id !== null && $user->loadMissing('company')->company?->status !== CompanyStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::error(
                'This company account is not active. Contact your administrator.',
                'COMPANY_INACTIVE',
                403,
            );
        }

        $context = $user
            ? new CompanyContext($user->company_id, $user->company_id === null)
            : CompanyContext::forGuest();

        app()->instance(CompanyContext::class, $context);

        // Scopes every Spatie role/permission lookup for the rest of this request.
        // Super Admin resolves to the global team (null), matching how the
        // "Super Admin" role itself is seeded.
        app(PermissionRegistrar::class)->setPermissionsTeamId($context->companyId());

        return $next($request);
    }
}