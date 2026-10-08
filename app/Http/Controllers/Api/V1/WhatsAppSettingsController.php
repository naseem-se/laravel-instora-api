<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WhatsAppProviderStatus;
use App\Exceptions\WhatsAppNotAuthorizedException;
use App\Exceptions\WhatsAppProviderNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\CompleteWhatsAppEmbeddedSignupRequest;
use App\Http\Requests\WhatsApp\TestWhatsAppMessageRequest;
use App\Http\Resources\WhatsAppProviderResource;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppEmbeddedSignupService;
use App\Services\WhatsApp\WhatsAppProviderResolver;
use App\Services\WhatsAppSettingsService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class WhatsAppSettingsController extends Controller
{
    public function __construct(private readonly WhatsAppSettingsService $settings) {}

    public function show(CompanyContext $context, WhatsAppProviderResolver $resolver): JsonResponse
    {
        $this->authorize('whatsapp.view');

        $companyId = $context->requireCompanyId();
        $ownProvider = $this->settings->find($companyId);
        $resolution = $resolver->resolve($companyId, actorUserId: request()->user()->id);

        return ApiResponse::success([
            'own_provider' => $ownProvider ? new WhatsAppProviderResource($ownProvider) : null,
            'effective_source' => $resolution->authorized ? $resolution->source : 'disabled',
            'unavailable_reason' => $resolution->authorized ? null : $resolution->skipReason,
            'embedded_signup' => MetaWhatsAppEmbeddedSignupService::signupConfig(),
        ]);
    }

    public function completeEmbeddedSignup(
        CompleteWhatsAppEmbeddedSignupRequest $request,
        CompanyContext $context,
        MetaWhatsAppEmbeddedSignupService $embeddedSignup,
    ): JsonResponse {
        $this->authorize('whatsapp.configure');

        $data = $request->validated();
        $provider = $embeddedSignup->connect(
            $context->requireCompanyId(),
            $data['code'],
            $data['waba_id'],
            $data['phone_number_id'] ?? null,
            $request->user(),
        );

        return ApiResponse::success(
            new WhatsAppProviderResource($provider),
            $provider->status === WhatsAppProviderStatus::Active
                ? 'WhatsApp Business connected successfully.'
                : 'WhatsApp was connected, but the connection could not be verified. Please test the connection.',
        );
    }

    public function destroy(CompanyContext $context): JsonResponse
    {
        $this->authorize('whatsapp.configure');

        $this->settings->delete($this->findConfiguredProvider($context), request()->user());

        return ApiResponse::success(null, 'WhatsApp configuration removed successfully.');
    }

    public function testConnection(CompanyContext $context): JsonResponse
    {
        $this->authorize('whatsapp.configure');

        $provider = $this->settings->verify($this->findConfiguredProvider($context));

        return ApiResponse::success(
            new WhatsAppProviderResource($provider),
            $provider->status === WhatsAppProviderStatus::Active ? 'Connection verified successfully.' : 'Connection test failed.'
        );
    }

    /** Test messages always use the authenticated company's own active provider. */
    public function testMessage(TestWhatsAppMessageRequest $request, CompanyContext $context, WhatsAppProviderResolver $resolver): JsonResponse
    {
        $this->authorize('whatsapp.send');

        $companyId = $context->requireCompanyId();
        $resolution = $resolver->resolve($companyId, actorUserId: $request->user()->id);

        if (! $resolution->authorized) {
            throw new WhatsAppNotAuthorizedException($resolution->skipReason);
        }

        $data = $request->validated();
        $result = $this->settings->sendTestMessage(
            $resolution->provider,
            $companyId,
            $data['recipient'],
            $data['message'] ?? null,
            $request->user(),
            $data['template_name'] ?? null,
            $data['template_language'] ?? null,
        );

        return $result->success
            ? ApiResponse::success(['provider_message_id' => $result->providerMessageId, 'source' => $resolution->source], 'Test message sent successfully.')
            : ApiResponse::error("The test message could not be sent: {$result->errorMessage}", $result->errorCode, 502);
    }

    private function findConfiguredProvider(CompanyContext $context): WhatsAppProvider
    {
        $provider = $this->settings->find($context->requireCompanyId());

        if (! $provider) {
            throw new WhatsAppProviderNotConfiguredException();
        }

        return $provider;
    }
}