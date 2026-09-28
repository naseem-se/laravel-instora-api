<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditAction;
use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            $this->audit->log(
                AuditAction::LoginFailed->value,
                newValues: ['email' => $credentials['email']],
            );

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = $request->user()->load('company');

        if ($user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['This account is not active. Contact your administrator.'],
            ]);
        }

        if ($user->company && $user->company->status !== CompanyStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['This company account is not active. Contact your administrator.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);

        $this->audit->log(
            AuditAction::Login->value,
            entity: $user,
            companyId: $user->company_id,
            userId: $user->id,
        );

        return ApiResponse::success(
            new UserResource($user->load(['company', 'roles'])),
            'Logged in successfully.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $this->audit->log(
                AuditAction::Logout->value,
                entity: $user,
                companyId: $user->company_id,
                userId: $user->id,
            );
        }

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load(['company', 'roles']))
        );
    }
}