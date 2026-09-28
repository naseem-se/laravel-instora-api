<?php

namespace App\Support;

class SystemDnsResolver implements DnsResolverInterface
{
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null,
            $records
        )));
    }
}