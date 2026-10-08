<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WhatsAppProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\Adapters\EvolutionApiProvider;
use App\Services\WhatsApp\SafeWhatsAppHttpClient;
use App\Services\WhatsAppSettingsService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles QR code connection flow for the self-hosted Evolution API provider.
 * All routes are authenticated (auth:sanctum).
 */
class EvolutionApiController extends Controller
{
    public function __construct(
        private readonly WhatsAppSettingsService $settings,
        private readonly SafeWhatsAppHttpClient $http,
    ) {}

    /**
     * Initialize (or re-initialize) an Evolution API session for this company
     * and return the QR code so the user can scan it with their phone.
     */
    public function initSession(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('whatsapp.configure');

        $companyId = $context->requireCompanyId();
        $baseUrl   = $this->evolutionUrl();
        $apiKey    = $this->evolutionKey();

        if (! $baseUrl || ! $apiKey) {
            return ApiResponse::error('Evolution API is not configured on this server.', 'EVOLUTION_NOT_CONFIGURED', 503);
        }

        // Find or create the WhatsApp provider record for this company.
        $provider = $this->settings->find($companyId);

        if (! $provider || $provider->provider_type !== 'evolution_api') {
            // Create a new provider record so we have a stable ID for the session name.
            $provider = new WhatsAppProvider();
            $provider->company_id   = $companyId;
            $provider->provider_type = 'evolution_api';
            $provider->name         = 'WhatsApp (QR Connect)';
            $provider->base_url     = $baseUrl;
            $provider->status       = WhatsAppProviderStatus::Inactive;
            $provider->created_by   = $request->user()->id;
            $provider->updated_by   = $request->user()->id;
            $provider->credentials  = [];
            $provider->save();
        }

        $sessionName = 'session_' . $provider->id;

        try {
            // Check if session already exists & is connected.
            $stateResponse = $this->http->get(
                "{$baseUrl}/instance/connectionState/{$sessionName}",
                $this->headers($apiKey),
            );

            if ($stateResponse->successful()) {
                $state = $stateResponse->json('instance.state') ?? $stateResponse->json('state') ?? '';
                if (in_array($state, ['open', 'CONNECTED'], true)) {
                    // Already connected — sync the phone number and activate the provider.
                    $this->activateProvider($provider, $baseUrl, $apiKey, $sessionName, $request->user()->id);
                    return ApiResponse::success([
                        'status'      => 'connected',
                        'qr_code'     => null,
                        'provider_id' => $provider->id,
                        'phone'       => $provider->sender_number,
                    ], 'WhatsApp is already connected.');
                }
            }

            // Create / restart the instance to get a fresh QR code.
            $createResponse = $this->http->post(
                "{$baseUrl}/instance/create",
                $this->headers($apiKey),
                [
                    'instanceName' => $sessionName,
                    'qrcode'       => true,
                    'integration'  => 'WHATSAPP-BAILEYS',
                ],
            );

            if ($createResponse->successful() || $createResponse->status() === 409 || $createResponse->status() === 403) {
                // 409/403 = instance already exists, fetch a fresh QR.
                $qrResponse = $this->http->get(
                    "{$baseUrl}/instance/connect/{$sessionName}",
                    $this->headers($apiKey),
                );

                $qrBase64 = $qrResponse->json('base64')
                    ?? $qrResponse->json('qrcode.base64')
                    ?? $createResponse->json('qrcode.base64')
                    ?? $createResponse->json('base64')
                    ?? null;

                return ApiResponse::success([
                    'status'      => 'qr_pending',
                    'qr_code'     => $qrBase64,
                    'provider_id' => $provider->id,
                ], 'Scan the QR code with your WhatsApp app.');
            }

            $error = $createResponse->json('message') ?? $createResponse->json('error') ?? 'Unknown error.';
            Log::error('EvolutionApi: failed to create instance.', [
                'company_id'  => $companyId,
                'status'      => $createResponse->status(),
                'error'       => $error,
            ]);

            return ApiResponse::error("Could not start WhatsApp session: {$error}", 'EVOLUTION_SESSION_ERROR', 502);
        } catch (Throwable $e) {
            Log::error('EvolutionApi: initSession exception.', [
                'company_id' => $companyId,
                'exception'  => $e->getMessage(),
            ]);

            return ApiResponse::error('Could not reach the WhatsApp server. Please try again.', 'EVOLUTION_UNREACHABLE', 503);
        }
    }

    /**
     * Poll endpoint — the frontend calls this every few seconds after showing the QR code.
     * Returns 'connected' when the user has scanned the QR code successfully.
     */
    public function sessionStatus(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('whatsapp.configure');

        $companyId = $context->requireCompanyId();
        $provider  = $this->settings->find($companyId);

        if (! $provider || $provider->provider_type !== 'evolution_api') {
            return ApiResponse::success(['status' => 'not_configured']);
        }

        $baseUrl     = $this->evolutionUrl();
        $apiKey      = $this->evolutionKey();
        $sessionName = 'session_' . $provider->id;

        try {
            $response = $this->http->get(
                "{$baseUrl}/instance/connectionState/{$sessionName}",
                $this->headers($apiKey),
            );

            if (! $response->successful()) {
                return ApiResponse::success(['status' => 'qr_pending']);
            }

            $state = $response->json('instance.state') ?? $response->json('state') ?? '';

            if (in_array($state, ['open', 'CONNECTED'], true)) {
                $this->activateProvider($provider, $baseUrl, $apiKey, $sessionName, $request->user()->id);
                return ApiResponse::success([
                    'status' => 'connected',
                    'phone'  => $provider->fresh()->sender_number,
                ]);
            }

            return ApiResponse::success(['status' => 'qr_pending', 'state' => $state]);
        } catch (Throwable $e) {
            return ApiResponse::success(['status' => 'qr_pending']);
        }
    }

    /**
     * Disconnect the WhatsApp session and remove the provider record.
     */
    public function disconnect(Request $request, CompanyContext $context): JsonResponse
    {
        $this->authorize('whatsapp.configure');

        $provider = $this->settings->find($context->requireCompanyId());

        if ($provider && $provider->provider_type === 'evolution_api') {
            $baseUrl     = $this->evolutionUrl();
            $apiKey      = $this->evolutionKey();
            $sessionName = 'session_' . $provider->id;

            // Attempt to delete the remote session (best-effort, don't fail on error).
            try {
                $this->http->delete("{$baseUrl}/instance/delete/{$sessionName}", $this->headers($apiKey));
            } catch (Throwable) {
                // Ignore remote errors — we still remove the local record.
            }

            $this->settings->delete($provider, $request->user());
        }

        return ApiResponse::success(null, 'WhatsApp disconnected successfully.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch the connected phone number from Evolution API, update the provider to Active.
     */
    private function activateProvider(
        WhatsAppProvider $provider,
        string $baseUrl,
        string $apiKey,
        string $sessionName,
        int $userId,
    ): void {
        try {
            $infoResponse = $this->http->get(
                "{$baseUrl}/instance/fetchInstances",
                $this->headers($apiKey),
            );

            $instances = $infoResponse->json() ?? [];
            $match     = collect($instances)->first(fn ($i) => ($i['instance']['instanceName'] ?? '') === $sessionName);
            $phone     = $match['instance']['owner'] ?? $match['instance']['jid'] ?? null;

            // Strip the "@s.whatsapp.net" suffix Evolution API appends.
            if ($phone && str_contains($phone, '@')) {
                $phone = '+' . explode('@', $phone)[0];
            }

            $provider->status        = WhatsAppProviderStatus::Active;
            $provider->sender_number = $phone ?? $provider->sender_number;
            $provider->last_error    = null;
            $provider->updated_by    = $userId;
            $provider->save();
        } catch (Throwable $e) {
            // Non-fatal: mark active even if we couldn't fetch the phone number.
            $provider->status     = WhatsAppProviderStatus::Active;
            $provider->last_error = null;
            $provider->save();

            Log::warning('EvolutionApi: could not fetch phone number after connection.', [
                'provider_id' => $provider->id,
                'exception'   => $e->getMessage(),
            ]);
        }
    }

    private function evolutionUrl(): ?string
    {
        return rtrim((string) config('services.evolution_api.url', ''), '/') ?: null;
    }

    private function evolutionKey(): ?string
    {
        return (string) config('services.evolution_api.key', '') ?: null;
    }

    private function headers(string $apiKey): array
    {
        return ['apikey' => $apiKey, 'Content-Type' => 'application/json'];
    }
}
