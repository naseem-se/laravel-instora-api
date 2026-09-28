<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccess;

/**
 * Implements Architecture.md #6.4's precedence exactly:
 *   Explicit user deny    -> deny
 *   Explicit user allow   -> continue only if company is allowed
 *   Company deny           -> deny
 *   Company allow          -> allow
 *   No record               -> deny
 *
 * $actorUserId is null for automated sends (the reminder scheduler has no
 * acting employee) - in that case only the company-level row is consulted,
 * per the Phase 12 decision notes.
 */
class WhatsAppAccessResolver
{
    public function isAuthorized(int $companyId, ?int $actorUserId): WhatsAppAccessDecision
    {
        if ($actorUserId !== null) {
            $userRecord = WhatsAppAccess::where('company_id', $companyId)->where('user_id', $actorUserId)->first();

            if ($userRecord && ! $userRecord->enabled) {
                return WhatsAppAccessDecision::denied('shared_provider_user_not_authorized');
            }

            // An explicit user allow (or no override at all) falls through
            // to the company check below - user allow is not sufficient on
            // its own if the company itself is disabled.
        }

        $companyRecord = WhatsAppAccess::where('company_id', $companyId)->whereNull('user_id')->first();

        if (! $companyRecord || ! $companyRecord->enabled) {
            return WhatsAppAccessDecision::denied('shared_provider_not_authorized');
        }

        return WhatsAppAccessDecision::authorized();
    }
}