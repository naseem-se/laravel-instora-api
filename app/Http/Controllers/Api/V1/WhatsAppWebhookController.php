<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Public routes - no auth:sanctum. Authenticity comes entirely from signature/token verification below. */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly WhatsAppWebhookService $webhooks) {}

    /**
     * Meta's WhatsApp Cloud API subscription handshake: called once when
     * the webhook URL is registered, expecting hub_challenge echoed back
     * verbatim if hub_verify_token matches. Generic providers never call this.
     */
    public function verify(Request $request, int $provider): Response
    {
        $providerModel = WhatsAppProvider::find($provider);

        if (! $providerModel) {
            throw new NotFoundHttpException();
        }

        $token = $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($request->query('hub_mode') !== 'subscribe' || ! $token || ! hash_equals((string) $providerModel->webhook_secret, (string) $token)) {
            throw new AccessDeniedHttpException('Invalid verify token.');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request, int $provider): JsonResponse
    {
        $providerModel = WhatsAppProvider::find($provider);

        if (! $providerModel) {
            throw new NotFoundHttpException();
        }

        if (! $this->webhooks->verifySignature($providerModel, $request)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $this->webhooks->process($providerModel, (array) $request->json()->all());

        return response()->json(['success' => true]);
    }

    public function verifyMeta(Request $request): Response
    {
        $verifyToken = (string) config('services.whatsapp.embedded_signup.webhook_verify_token');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        if (
            $request->query('hub_mode') !== 'subscribe'
            || $verifyToken === ''
            || ! hash_equals($verifyToken, $token)
        ) {
            throw new AccessDeniedHttpException('Invalid verify token.');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handleMeta(Request $request): JsonResponse
    {
        $appSecret = (string) config('services.whatsapp.embedded_signup.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        if ($appSecret === '' || ! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $payload = (array) $request->json()->all();

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $phoneNumberId = $change['value']['metadata']['phone_number_id'] ?? null;

                if (! is_string($phoneNumberId) || $phoneNumberId === '') {
                    continue;
                }

                $providerQuery = WhatsAppProvider::where('provider_type', 'whatsapp_cloud')
                    ->where('phone_number_id', $phoneNumberId);
                $wabaId = $entry['id'] ?? null;

                if (is_string($wabaId) && $wabaId !== '') {
                    $providerQuery->where('business_account_id', $wabaId);
                }

                $provider = $providerQuery->first();

                if (! $provider) {
                    Log::warning('Meta WhatsApp webhook received an event for an unconnected number.', [
                        'phone_number_id' => $phoneNumberId,
                    ]);

                    continue;
                }

                $this->webhooks->process($provider, [
                    'entry' => [[
                        'id' => $wabaId,
                        'changes' => [$change],
                    ]],
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}