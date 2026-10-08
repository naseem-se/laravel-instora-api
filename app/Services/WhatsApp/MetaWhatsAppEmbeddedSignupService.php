<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppProviderStatus;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\WhatsAppProvider;
use App\Services\WhatsAppSettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppEmbeddedSignupService
{
    private const GRAPH_HOST = 'https://graph.facebook.com';

    public function __construct(private readonly WhatsAppSettingsService $settings) {}

    public function connect(int $companyId, string $code, string $wabaId, ?string $phoneNumberId, User $actor): WhatsAppProvider
    {
        $config = config('services.whatsapp.embedded_signup');
        $appId = $config['app_id'] ?? null;
        $appSecret = $config['app_secret'] ?? null;

        if (! self::isConfigured()) {
            throw new BusinessException('WhatsApp Embedded Signup is not configured. Contact your administrator.', 'WHATSAPP_EMBEDDED_SIGNUP_NOT_CONFIGURED', 503);
        }

        $version = $config['graph_api_version'] ?? 'v25.0';
        $baseUrl = self::GRAPH_HOST."/{$version}";

        $tokenResponse = $this->graphRequest(fn () => Http::timeout(15)->get("{$baseUrl}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
        ]));
        $this->ensureSuccessful($tokenResponse, 'Meta could not authorize this WhatsApp connection.');
        $accessToken = $tokenResponse->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new BusinessException('Meta did not return an access token for this WhatsApp connection.', 'WHATSAPP_EMBEDDED_SIGNUP_INVALID_RESPONSE', 422);
        }

        $phoneResponse = $this->graphRequest(fn () => Http::withToken($accessToken)->timeout(15)->get("{$baseUrl}/{$wabaId}/phone_numbers", [
            'fields' => 'id,display_phone_number',
            'limit' => 100,
        ]));
        $this->ensureSuccessful($phoneResponse, 'Meta could not verify the selected WhatsApp number for this business account.');
        $availablePhones = collect($phoneResponse->json('data', []));
        $phone = $phoneNumberId
            ? $availablePhones->firstWhere('id', $phoneNumberId)
            : ($availablePhones->count() === 1 ? $availablePhones->first() : null);

        if (! $phone && ! $phoneNumberId && $availablePhones->count() > 1) {
            throw new BusinessException('This WhatsApp Business account has multiple numbers. Please select one phone number and reconnect.', 'WHATSAPP_PHONE_NUMBER_SELECTION_REQUIRED', 422);
        }

        $phoneNumberId = $phone['id'] ?? null;
        $displayNumber = $phone['display_phone_number'] ?? null;

        if (! $phone || ! is_string($displayNumber) || $displayNumber === '') {
            throw new BusinessException('The selected WhatsApp number could not be verified for this business account.', 'WHATSAPP_EMBEDDED_SIGNUP_ACCOUNT_MISMATCH', 422);
        }

        $alreadyConnected = WhatsAppProvider::where('phone_number_id', $phoneNumberId)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', '!=', $companyId);
            })
            ->exists();

        if ($alreadyConnected) {
            throw new BusinessException('This WhatsApp number is already connected to another Instora company.', 'WHATSAPP_NUMBER_ALREADY_CONNECTED', 409);
        }

        $subscriptionResponse = $this->graphRequest(fn () => Http::withToken($accessToken)
            ->timeout(15)
            ->post("{$baseUrl}/{$wabaId}/subscribed_apps"));
        $this->ensureSuccessful($subscriptionResponse, 'Meta could not subscribe this business account to delivery status updates.');

        $existingProvider = WhatsAppProvider::where('company_id', $companyId)
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        $provider = $this->settings->upsert($companyId, [
            'name' => 'WhatsApp Business',
            'provider_type' => 'whatsapp_cloud',
            'base_url' => self::GRAPH_HOST,
            'api_version' => $version,
            'phone_number_id' => $phoneNumberId,
            'business_account_id' => $wabaId,
            'sender_number' => $displayNumber,
            'credentials' => ['access_token' => $accessToken],
            'replace_credentials' => true,
        ], $actor, $existingProvider, createNew: $existingProvider === null);

        if ($provider->status === WhatsAppProviderStatus::Active) {
            WhatsAppProvider::where('company_id', $companyId)
                ->where('id', '!=', $provider->id)
                ->where('status', WhatsAppProviderStatus::Active)
                ->get()
                ->each(fn (WhatsAppProvider $previous) => $this->settings->delete($previous, $actor));
        }

        return $provider->fresh();
    }

    public static function isConfigured(): bool
    {
        $config = config('services.whatsapp.embedded_signup');

        return filled($config['app_id'] ?? null)
            && filled($config['config_id'] ?? null)
            && filled($config['app_secret'] ?? null)
            && filled($config['webhook_verify_token'] ?? null);
    }

    public static function signupConfig(): ?array
    {
        if (! self::isConfigured()) {
            return null;
        }

        return [
            'app_id' => config('services.whatsapp.embedded_signup.app_id'),
            'config_id' => config('services.whatsapp.embedded_signup.config_id'),
            'graph_api_version' => config('services.whatsapp.embedded_signup.graph_api_version', 'v25.0'),
        ];
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('Meta WhatsApp Embedded Signup API request failed.', [
            'http_status' => $response->status(),
            'meta_error_code' => $response->json('error.code'),
            'meta_error_type' => $response->json('error.type'),
        ]);

        throw new BusinessException($message, 'WHATSAPP_EMBEDDED_SIGNUP_FAILED', 422);
    }

    private function graphRequest(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            report($exception);

            throw new BusinessException('Unable to reach Meta. Please try connecting your WhatsApp number again.', 'WHATSAPP_META_CONNECTION_FAILED', 502);
        }
    }
}
