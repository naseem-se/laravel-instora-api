<?php

namespace App\Support;

use App\Exceptions\BusinessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        // Let the default handler deal with non-API traffic.
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'Validation failed.', 'VALIDATION_ERROR', 422, $e->errors()
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                'Unauthenticated.', 'AUTHENTICATION_ERROR', 401
            ),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::error(
                'This action is not authorized.', 'AUTHORIZATION_ERROR', 403
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error(
                'Resource not found.', 'NOT_FOUND', 404
            ),
            $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                'Too many requests. Please try again later.', 'RATE_LIMITED', 429
            ),
            $e instanceof BusinessException => ApiResponse::error(
                $e->getMessage(), $e->errorCode(), $e->status()
            ),
            default => self::unexpected($e),
        };
    }

    private static function unexpected(Throwable $e): JsonResponse
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        // Only local/debug environments see the real message; production never does.
        $message = config('app.debug')
            ? $e->getMessage()
            : 'An unexpected error occurred.';

        return ApiResponse::error($message, 'INTERNAL_ERROR', $status);
    }
}