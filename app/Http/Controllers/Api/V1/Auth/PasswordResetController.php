<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Always returns the same response regardless of whether the email
     * belongs to an account - Security.md #5's account-enumeration rule.
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::success(
            null,
            'If an account exists for that email address, a password reset link has been sent.'
        );
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill(['password' => $password])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $resetUser = $user;

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetUser instanceof User) {
            return ApiResponse::error($this->translateStatus($status), 'PASSWORD_RESET_FAILED', 422);
        }

        $this->audit->log(
            AuditAction::PasswordReset->value,
            entity: $resetUser,
            companyId: $resetUser->company_id,
            userId: $resetUser->id,
        );

        return ApiResponse::success(null, 'Your password has been reset successfully. Please sign in.');
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN, Password::INVALID_USER =>
                'This password reset link is invalid or has expired. Please request a new one.',
            default => 'Unable to reset your password. Please try again.',
        };
    }
}