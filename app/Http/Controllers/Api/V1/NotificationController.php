<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Requests\Notification\RetryNotificationRequest;
use App\Http\Resources\NotificationLogListResource;
use App\Http\Resources\NotificationLogResource;
use App\Models\NotificationLog;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(ListNotificationsRequest $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('notifications.view');

        $query = NotificationLog::with('customer')->where('company_id', $context->requireCompanyId());

        foreach (['status', 'channel', 'type', 'customer_id'] as $filter) {
            if ($value = $request->input($filter)) {
                $query->where($filter, $value);
            }
        }

        $query->orderByDesc('created_at');

        $logs = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(NotificationLogListResource::collection($logs)->response()->getData(true));
    }

    public function show(int $id, CompanyContext $context): JsonResponse
    {
        $this->authorize('notifications.view');

        $log = $this->findOwned(NotificationLog::class, $id, $context);

        return ApiResponse::success(new NotificationLogResource($log->load(['customer', 'deliveryAttempts'])));
    }

    public function retry(RetryNotificationRequest $request, int $id, CompanyContext $context): JsonResponse
    {
        $this->authorize('notifications.manage');

        $log = $this->findOwned(NotificationLog::class, $id, $context);
        $log = $this->notifications->retry($log, $request->user(), $request->validated()['reason'] ?? null);

        return ApiResponse::success(new NotificationLogResource($log->load('deliveryAttempts')), 'Notification queued for retry.');
    }
}