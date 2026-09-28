<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\WhatsAppProviderStatus;
use App\Models\User;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\MessagePayload;
use App\Services\WhatsApp\SendResult;
use App\Services\WhatsApp\TemplatePayload;
use App\Services\WhatsApp\WhatsAppProviderAdapterFactory;
use Illuminate\Support\Carbon;

class WhatsAppSettingsService
{
    public function __construct(
        private readonly WhatsAppProviderAdapterFactory $adapters,
        private readonly AuditLogger $audit,
    ) {}

    /** $companyId is null for the Super Admin platform provider. */
    public function find(?int $companyId): ?WhatsAppProvider
    {
        return $companyId === null
            ? WhatsAppProvider::whereNull('company_id')->first()
            : WhatsAppProvider::where('company_id', $companyId)->first();
    }

    public function upsert(?int $companyId, array $data, User $actor): WhatsAppProvider
    {
        $provider = $this->find($companyId) ?? new WhatsAppProvider();
        $isNew = ! $provider->exists;

        $provider->fill([
            'name' => $data['name'],
            'provider_type' => $data['provider_type'],
            'base_url' => $data['base_url'],
            'api_version' => $data['api_version'] ?? null,
            'phone_number_id' => $data['phone_number_id'] ?? null,
            'business_account_id' => $data['business_account_id'] ?? null,
            'sender_number' => $data['sender_number'] ?? null,
        ]);

        $provider->company_id = $companyId;
        $provider->status = WhatsAppProviderStatus::Inactive;
        $provider->created_by ??= $actor->id;
        $provider->updated_by = $actor->id;

        // Safely retrieve existing credentials. If decryption fails (e.g., APP_KEY changed
        // since they were stored), discard them and start fresh with only the new values.
        try {
            $credentials = $provider->exists ? ($provider->credentials ?? []) : [];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WhatsApp: could not decrypt existing credentials; starting fresh.', [
                'provider_id' => $provider->id ?? 'new',
                'exception' => $e->getMessage(),
            ]);
            $credentials = [];
        }

        if (! is_array($credentials)) {
            $credentials = [];
        }

        foreach ($data['credentials'] ?? [] as $key => $value) {
            if ($value !== null && $value !== '') {
                $credentials[$key] = $value;
            }
        }
        $provider->credentials = $credentials;

        if (! empty($data['webhook_secret'])) {
            $provider->webhook_secret = $data['webhook_secret'];
        }

        $provider->save();
        $this->verify($provider);

        $this->audit->log(
            $isNew ? AuditAction::WhatsAppProviderConfigured->value : AuditAction::WhatsAppProviderUpdated->value,
            entity: $provider,
            newValues: ['provider_type' => $provider->provider_type, 'name' => $provider->name],
            companyId: $companyId,
            userId: $actor->id,
        );

        return $provider->fresh();
    }

    public function verify(WhatsAppProvider $provider): WhatsAppProvider
    {
        $health = $this->adapters->make($provider)->verifyConfiguration();

        $provider->status = $health->healthy ? WhatsAppProviderStatus::Active : WhatsAppProviderStatus::Error;
        $provider->last_verified_at = Carbon::now();
        $provider->last_error = $health->healthy ? null : $health->message;
        $provider->save();

        \Illuminate\Support\Facades\Log::info('WhatsApp: provider verification completed.', [
            'provider_id' => $provider->id,
            'status' => $provider->status->value,
            'healthy' => $health->healthy,
            'error' => $health->message,
        ]);

        return $provider;
    }

    public function delete(WhatsAppProvider $provider, User $actor): void
    {
        $companyId = $provider->company_id;
        $provider->delete();

        $this->audit->log(
            AuditAction::WhatsAppProviderDeleted->value,
            oldValues: ['provider_type' => $provider->provider_type],
            companyId: $companyId,
            userId: $actor->id,
        );
    }

    public function sendTestMessage(
        WhatsAppProvider $provider,
        ?int $actingCompanyId,
        string $recipient,
        ?string $message,
        User $actor,
        ?string $templateName = null,
        ?string $templateLanguage = null,
    ): SendResult {
        $adapter = $this->adapters->make($provider);

        if ($templateName) {
            $components = [];
            if ($message) {
                $parameters = [];
                // Support comma-separated values for template variables (e.g., {{1}}, {{2}})
                $values = array_map('trim', explode(',', $message));
                foreach ($values as $val) {
                    if ($val !== '') {
                        $parameters[] = ['type' => 'text', 'text' => $val];
                    }
                }
                
                if (!empty($parameters)) {
                    $components = [
                        [
                            'type' => 'body',
                            'parameters' => $parameters,
                        ],
                    ];
                }
            }

            $result = $adapter->sendTemplate(new TemplatePayload(
                recipient: $recipient,
                templateName: $templateName,
                languageCode: $templateLanguage ?? 'en',
                components: $components,
            ));
        } else {
            $result = $adapter->sendText(new MessagePayload(
                $recipient,
                $message ?: 'This is a test message from your Installment Management System.',
            ));
        }

        $this->audit->log(
            AuditAction::WhatsAppTestMessageSent->value,
            entity: $provider,
            newValues: [
                'recipient' => $recipient,
                'success' => $result->success,
                'source' => $provider->company_id === null ? 'shared' : 'own',
                'type' => $templateName ? 'template' : 'text',
            ],
            companyId: $actingCompanyId,
            userId: $actor->id,
        );

        return $result;
    }
}