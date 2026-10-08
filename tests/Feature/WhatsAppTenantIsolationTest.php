<?php

namespace Tests\Feature;

use App\Enums\WhatsAppProviderStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppEmbeddedSignupService;
use App\Services\WhatsApp\WhatsAppProviderResolver;
use App\Services\WhatsAppService;
use App\Support\DnsResolverInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $this->app->instance(DnsResolverInterface::class, new class implements DnsResolverInterface
        {
            public function resolve(string $host): array
            {
                return ['1.1.1.1'];
            }
        });
    }

    public function test_two_companies_send_only_from_their_own_numbers_and_tokens(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $providerA = $this->makeProvider($companyA, 'phone-a', 'waba-a', 'token-company-a');
        $providerB = $this->makeProvider($companyB, 'phone-b', 'waba-b', 'token-company-b');
        $notificationA = $this->makeNotification($companyA);
        $notificationB = $this->makeNotification($companyB);

        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.test']]], 200));

        $whatsapp = app(WhatsAppService::class);
        $resultA = $whatsapp->sendText($notificationA, $companyA->id, 1, '+923001111111', 'Reminder A');
        $resultB = $whatsapp->sendText($notificationB, $companyB->id, 2, '+923012222222', 'Reminder B');

        $this->assertTrue($resultA->sendResult->success);
        $this->assertTrue($resultB->sendResult->success);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v25.0/phone-a/messages')
            && $request->hasHeader('Authorization', 'Bearer token-company-a'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v25.0/phone-b/messages')
            && $request->hasHeader('Authorization', 'Bearer token-company-b'));
        Http::assertSentCount(2);

        $this->assertDatabaseHas('whatsapp_messages', [
            'company_id' => $companyA->id,
            'notification_id' => $notificationA,
            'provider_id' => $providerA->id,
            'provider_message_id' => 'wamid.test',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'company_id' => $companyB->id,
            'notification_id' => $notificationB,
            'provider_id' => $providerB->id,
            'provider_message_id' => 'wamid.test',
        ]);
    }

    public function test_tenant_without_its_own_number_does_not_fall_back_to_platform_number(): void
    {
        $company = Company::factory()->create();
        $platformProvider = $this->makeProvider(null, 'platform-phone', 'platform-waba', 'platform-token');

        $resolution = app(WhatsAppProviderResolver::class)->resolve($company->id);

        $this->assertFalse($resolution->authorized);
        $this->assertNull($resolution->provider);
        $this->assertSame('no_company_whatsapp_provider', $resolution->skipReason);
        $this->assertNull($platformProvider->company_id);
    }

    public function test_company_credentials_are_encrypted_at_rest(): void
    {
        $company = Company::factory()->create();
        $provider = $this->makeProvider($company, 'phone-a', 'waba-a', 'company-secret-token');
        $storedCredentials = DB::table('whatsapp_providers')->where('id', $provider->id)->value('credentials');

        $this->assertStringNotContainsString('company-secret-token', $storedCredentials);
        $this->assertSame('company-secret-token', $provider->fresh()->credentials['access_token']);
    }

    public function test_meta_embedded_signup_verifies_and_persists_the_company_number(): void
    {
        $company = Company::factory()->create();
        $actor = User::factory()->for($company)->create();
        config(['services.whatsapp.embedded_signup' => [
            'app_id' => 'meta-app-id',
            'app_secret' => 'meta-app-secret',
            'config_id' => 'signup-config-id',
            'graph_api_version' => 'v25.0',
            'webhook_verify_token' => 'webhook-verify-token',
        ]]);

        $phoneDetails = [
            'id' => 'phone-meta',
            'display_phone_number' => '+923001234567',
            'whatsapp_business_account' => ['id' => 'waba-meta'],
        ];
        Http::fake([
            'graph.facebook.com/v25.0/oauth/access_token*' => Http::response(['access_token' => 'meta-company-token'], 200),
            'graph.facebook.com/v25.0/waba-meta/phone_numbers*' => Http::response(['data' => [$phoneDetails]], 200),
            'graph.facebook.com/v25.0/phone-meta*' => Http::response($phoneDetails, 200),
            'graph.facebook.com/v25.0/waba-meta/subscribed_apps' => Http::response(['success' => true], 200),
        ]);

        $provider = app(MetaWhatsAppEmbeddedSignupService::class)
            ->connect($company->id, 'single-use-code', 'waba-meta', null, $actor);

        $this->assertSame($company->id, $provider->company_id);
        $this->assertSame('phone-meta', $provider->phone_number_id);
        $this->assertSame('waba-meta', $provider->business_account_id);
        $this->assertSame('+923001234567', $provider->sender_number);
        $this->assertSame(WhatsAppProviderStatus::Active, $provider->status);
        $this->assertSame('meta-company-token', $provider->credentials['access_token']);
        $expectedAuthorization = 'Bearer '.implode('-', ['meta', 'company', 'token']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v25.0/waba-meta/subscribed_apps')
            && $request->hasHeader('Authorization', $expectedAuthorization));
    }

    private function makeProvider(?Company $company, string $phoneNumberId, string $wabaId, string $token): WhatsAppProvider
    {
        $provider = new WhatsAppProvider([
            'name' => 'WhatsApp Business',
            'provider_type' => 'whatsapp_cloud',
            'base_url' => 'https://graph.facebook.com',
            'api_version' => 'v25.0',
            'phone_number_id' => $phoneNumberId,
            'business_account_id' => $wabaId,
            'sender_number' => '+923000000000',
            'credentials' => ['access_token' => $token],
        ]);
        $provider->company_id = $company?->id;
        $provider->status = WhatsAppProviderStatus::Active;
        $provider->save();

        return $provider;
    }

    private function makeNotification(Company $company): int
    {
        return DB::table('notification_logs')->insertGetId([
            'company_id' => $company->id,
            'type' => 'installment_due',
            'channel' => 'whatsapp',
            'recipient' => '+923000000000',
            'status' => 'sending',
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
