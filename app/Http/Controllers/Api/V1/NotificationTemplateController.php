<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpsertNotificationTemplateRequest;
use App\Services\NotificationTemplateService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class NotificationTemplateController extends Controller
{
    public function __construct(private readonly NotificationTemplateService $templates) {}

    public function index(CompanyContext $context): JsonResponse
    {
        // Superadmins bypass company-scoped permission checks
        if (!$context->isSuperAdmin()) {
            $this->authorize('notifications.manage');
        }

        $rows = $this->templates->effectiveList($context->companyId());

        return ApiResponse::success([
            'data' => array_map(fn ($row) => [
                'type' => $row['type'],
                'channel' => $row['channel']->value,
                'is_company_override' => $row['is_company_override'],
                'subject' => $row['template']?->subject,
                'body' => $row['template']?->body,
                'whatsapp_template_name' => $row['template']?->whatsapp_template_name,
                'whatsapp_template_language' => $row['template']?->whatsapp_template_language,
                'version' => $row['template']?->version,
            ], $rows),
        ]);
    }

    public function update(UpsertNotificationTemplateRequest $request, CompanyContext $context): JsonResponse
    {
        if (!$request->user()->hasRole('Super Admin')) {
            abort(403, 'Only superadmins can manage notification templates.');
        }

        $data = $request->validated();

        $template = $this->templates->upsert(
            companyId: $context->companyId(),
            type: NotificationType::from($data['type']),
            channel: NotificationChannel::from($data['channel']),
            subject: $data['subject'] ?? null,
            body: $data['body'],
            actor: $request->user(),
            whatsappTemplateName: $data['whatsapp_template_name'] ?? null,
            whatsappTemplateLanguage: $data['whatsapp_template_language'] ?? null,
        );

        return ApiResponse::success([
            'type' => $template->type,
            'channel' => $template->channel->value,
            'subject' => $template->subject,
            'body' => $template->body,
            'whatsapp_template_name' => $template->whatsapp_template_name,
            'whatsapp_template_language' => $template->whatsapp_template_language,
            'version' => $template->version,
        ], 'Template updated successfully.');
    }
}