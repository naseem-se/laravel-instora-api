<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationTemplateService
{
    public function upsert(
        ?int $companyId,
        NotificationType $type,
        NotificationChannel $channel,
        ?string $subject,
        string $body,
        User $actor,
        ?string $whatsappTemplateName = null,
        ?string $whatsappTemplateLanguage = null,
    ): NotificationTemplate {
        return DB::transaction(function () use ($companyId, $type, $channel, $subject, $body, $actor, $whatsappTemplateName, $whatsappTemplateLanguage) {
            $query = NotificationTemplate::where('type', $type->value)
                ->where('channel', $channel)
                ->where('is_active', true);

            if ($companyId === null) {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $companyId);
            }

            $existing = $query->first();

            $version = 1;

            if ($existing) {
                $version = $existing->version + 1;
                $existing->is_active = false;
                $existing->save();
            }

            $template = new NotificationTemplate();
            $template->company_id = $companyId;
            $template->type = $type->value;
            $template->channel = $channel;
            $template->version = $version;
            $template->subject = $subject;
            $template->body = $body;
            $template->whatsapp_template_name = $whatsappTemplateName;
            $template->whatsapp_template_language = $whatsappTemplateLanguage;
            $template->is_active = true;
            $template->created_by = $actor->id;
            $template->save();

            return $template;
        });
    }

    /**
     * @return list<array{type: string, channel: NotificationChannel, template: ?NotificationTemplate, is_company_override: bool}>
     */
    public function effectiveList(?int $companyId): array
    {
        $rows = [];

        foreach (NotificationType::cases() as $type) {
            foreach (NotificationChannel::cases() as $channel) {
                $companyTemplate = null;

                // Only look for company overrides when we have a company context
                if ($companyId !== null) {
                    $companyTemplate = NotificationTemplate::where('company_id', $companyId)
                        ->where('type', $type->value)->where('channel', $channel)->where('is_active', true)->first();
                }

                // Fall back to global (system default) template
                $template = $companyTemplate ?? NotificationTemplate::whereNull('company_id')
                    ->where('type', $type->value)->where('channel', $channel)->where('is_active', true)->first();

                $rows[] = [
                    'type' => $type->value,
                    'channel' => $channel,
                    'template' => $template,
                    'is_company_override' => (bool) $companyTemplate,
                ];
            }
        }

        return $rows;
    }
}