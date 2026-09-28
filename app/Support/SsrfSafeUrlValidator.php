<?php

namespace App\Support;

class SsrfSafeUrlValidator
{
    public function __construct(private readonly DnsResolverInterface $dns) {}

    /**
     * A URL is safe only if its scheme is HTTPS and every IP it resolves to
     * (or literally is) is a public, non-reserved address. Literal IPs skip
     * DNS entirely; hostnames are resolved fresh on every call - this method
     * is called again immediately before every actual send, not just at
     * save time, which is what defeats DNS rebinding.
     */
    public function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = $parts['host'] ?? null;

        if (! $host || strtolower($host) === 'localhost') {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->dns->resolve($host);

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}