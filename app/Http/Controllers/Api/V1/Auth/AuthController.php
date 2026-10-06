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

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
    
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }
    
        $user = Auth::user();

        if ($user->company_id !== null && $user->company?->status !== CompanyStatus::Active) {
            Auth::guard('web')->logout();

            return ApiResponse::error(
                'This company account is suspended. Contact your administrator.',
                'COMPANY_INACTIVE',
                403,
            );
        }
    
        // Revoke old tokens if desired, then generate a new token
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'token' => $token, // <--- Return token to React
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

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