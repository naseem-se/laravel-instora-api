<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppWebhookService;
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
}