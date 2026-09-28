<?php

namespace App\Support;

interface DnsResolverInterface
{
    /** @return list<string> resolved IPv4/IPv6 addresses, empty if unresolvable. */
    public function resolve(string $host): array;
}