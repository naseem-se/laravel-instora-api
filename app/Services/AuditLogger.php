<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request as RequestFacade;

class AuditLogger
{
    /**
     * Secret-shaped keys are stripped before persisting, regardless of caller
     * intent. See Security.md #24 - never store secrets in old/new values.
     */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token', 'api_key',
        'api_secret', 'secret', 'webhook_secret', 'credentials',
    ];

    public function log(
        string $action,
        ?Model $entity = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $companyId = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : 'System',
            'entity_id' => $entity?->getKey(),
            'old_values' => $this->redact($oldValues) ?: null,
            'new_values' => $this->redact($newValues) ?: null,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
        ]);
    }

    private function redact(array $values): array
    {
        foreach (self::REDACTED_KEYS as $key) {
            unset($values[$key]);
        }

        return $values;
    }
}