<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\WhatsAppProviderStatus;
use App\Exceptions\WhatsAppProviderDisabledException;
use App\Exceptions\WhatsAppProviderNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\StoreWhatsAppProviderRequest;
use App\Http\Requests\WhatsApp\TestWhatsAppMessageRequest;
use App\Http\Resources\WhatsAppProviderResource;
use App\Models\WhatsAppProvider;
use App\Services\WhatsAppSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** Platform-level provider (company_id = null). Same StoreWhatsAppProviderRequest as the company controller - the validation rules don't differ. */
class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppSettingsService $settings) {}

    public function show(): JsonResponse
    {
        $provider = $this->settings->find(null);

        return ApiResponse::success($provider ? new WhatsAppProviderResource($provider) : ['configured' => false]);
    }

    public function store(StoreWhatsAppProviderRequest $request): JsonResponse
    {
        $provider = $this->settings->upsert(null, $request->validated(), $request->user());

        return ApiResponse::success(new WhatsAppProviderResource($provider), 'Platform WhatsApp configuration saved successfully.');
    }

    public function destroy(): JsonResponse
    {
        $this->settings->delete($this->findConfigured(), request()->user());

        return ApiResponse::success(null, 'Platform WhatsApp configuration removed successfully.');
    }

    public function testConnection(): JsonResponse
    {
        $provider = $this->settings->verify($this->findConfigured());

        return ApiResponse::success(
            new WhatsAppProviderResource($provider),
            $provider->status === WhatsAppProviderStatus::Active ? 'Connection verified successfully.' : 'Connection test failed.'
        );
    }

    public function testMessage(TestWhatsAppMessageRequest $request): JsonResponse
    {
        $provider = $this->findConfigured();

        if ($provider->status !== WhatsAppProviderStatus::Active) {
            throw new WhatsAppProviderDisabledException();
        }

        $data = $request->validated();
        $result = $this->settings->sendTestMessage(
            $provider,
            null,
            $data['recipient'],
            $data['message'] ?? null,
            $request->user(),
            $data['template_name'] ?? null,
            $data['template_language'] ?? null,
        );

        return $result->success
            ? ApiResponse::success(['provider_message_id' => $result->providerMessageId], 'Test message sent successfully.')
            : ApiResponse::error("The test message could not be sent: {$result->errorMessage}", $result->errorCode, 502);
    }

    private function findConfigured(): WhatsAppProvider
    {
        $provider = $this->settings->find(null);

        if (! $provider) {
            throw new WhatsAppProviderNotConfiguredException();
        }

        return $provider;
    }
}