<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly PlatformReportService $reports) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->reports->dashboard());
    }
}