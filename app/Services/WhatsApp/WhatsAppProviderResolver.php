<?php

namespace App\Services\WhatsApp;

use App\Enums\CompanyStatus;
use App\Enums\WhatsAppProviderStatus;
use App\Models\Company;
use App\Models\WhatsAppProvider;

class WhatsAppProviderResolver
{
    public function resolve(int $companyId, ?int $customerId = null, ?int $actorUserId = null): WhatsAppResolution
    {
        $company = Company::find($companyId);

        if (! $company || $company->status !== CompanyStatus::Active) {
            return WhatsAppResolution::skipped('company_suspended');
        }

        $ownProvider = WhatsAppProvider::where('company_id', $companyId)
            ->where('status', WhatsAppProviderStatus::Active)
            ->orderByDesc('id')
            ->first();

        if ($ownProvider) {
            return WhatsAppResolution::authorized($ownProvider, 'own');
        }

        return WhatsAppResolution::skipped('no_company_whatsapp_provider');
    }
}