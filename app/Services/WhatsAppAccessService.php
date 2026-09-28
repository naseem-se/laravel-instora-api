<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use App\Models\WhatsAppAccess;

class WhatsAppAccessService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function setCompanyAccess(int $companyId, bool $enabled, User $actor): WhatsAppAccess
    {
        $access = WhatsAppAccess::where('company_id', $companyId)->whereNull('user_id')->first() ?? new WhatsAppAccess();
        $original = $access->enabled ?? null;

        $access->company_id = $companyId;
        $access->user_id = null;
        $access->enabled = $enabled;
        $access->created_by ??= $actor->id;
        $access->updated_by = $actor->id;
        $access->save();

        $this->audit->log(
            AuditAction::WhatsAppAccessChanged->value,
            entity: $access,
            oldValues: ['enabled' => $original],
            newValues: ['company_id' => $companyId, 'user_id' => null, 'enabled' => $enabled],
            companyId: null, // Super Admin platform action - see Security.md #5
            userId: $actor->id,
        );

        return $access;
    }

    /** $enabled === null removes the override, so the user falls back to the company's policy. */
    public function setUserAccess(int $companyId, int $userId, ?bool $enabled, User $actor): ?WhatsAppAccess
    {
        $access = WhatsAppAccess::where('company_id', $companyId)->where('user_id', $userId)->first();

        if ($enabled === null) {
            $access?->delete();

            $this->audit->log(
                AuditAction::WhatsAppAccessChanged->value,
                newValues: ['company_id' => $companyId, 'user_id' => $userId, 'enabled' => null, 'action' => 'override_removed'],
                companyId: null,
                userId: $actor->id,
            );

            return null;
        }

        $original = $access?->enabled;
        $access ??= new WhatsAppAccess();
        $access->company_id = $companyId;
        $access->user_id = $userId;
        $access->enabled = $enabled;
        $access->created_by ??= $actor->id;
        $access->updated_by = $actor->id;
        $access->save();

        $this->audit->log(
            AuditAction::WhatsAppAccessChanged->value,
            entity: $access,
            oldValues: ['enabled' => $original],
            newValues: ['company_id' => $companyId, 'user_id' => $userId, 'enabled' => $enabled],
            companyId: null,
            userId: $actor->id,
        );

        return $access;
    }
}