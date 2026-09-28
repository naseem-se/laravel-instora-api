<?php

namespace App\Services\WhatsApp;

use App\Support\SsrfSafeUrlValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The only place provider HTTP calls are made from. Every call is
 * SSRF-checked immediately before it happens, regardless of whether the
 * same URL was already validated at save time - see the Phase 11 decision
 * notes on DNS rebinding.
 */
class SafeWhatsAppHttpClient
{
    public function __construct(private readonly SsrfSafeUrlValidator $validator) {}

    public function get(string $url, array $headers = [], int $timeoutSeconds = 10): Response
    {
        $this->assertSafe($url);

        return Http::withHeaders($headers)->timeout($timeoutSeconds)->get($url);
    }

    public function post(string $url, array $headers, array $json, int $timeoutSeconds = 15): Response
    {
        $this->assertSafe($url);

        return Http::withHeaders($headers)->timeout($timeoutSeconds)->post($url, $json);
    }

    private function assertSafe(string $url): void
    {
        if (! $this->validator->isSafe($url)) {
            throw new RuntimeException('Refusing to call an unsafe or unreachable WhatsApp provider endpoint.');
        }
    }
}