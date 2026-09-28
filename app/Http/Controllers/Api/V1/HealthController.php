<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'application' => config('app.name'),
            'environment' => app()->environment(),
            'database' => $this->databaseStatus(),
            'timestamp' => now()->toIso8601String(),
        ], 'Service is healthy.');
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'connected';
        } catch (Throwable $e) {
            report($e);

            return 'unavailable';
        }
    }
}