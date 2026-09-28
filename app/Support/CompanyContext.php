<?php

namespace App\Support;

readonly class CompanyContext
{
    public function __construct(
        private ?int $companyId,
        private bool $isSuperAdmin,
    ) {}

    public static function forGuest(): self
    {
        return new self(null, false);
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    /**
     * @throws \RuntimeException if called outside a company-scoped request.
     */
    public function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \RuntimeException(
                'No company context is available for this request. This usually means '.
                'a Super Admin hit a company-scoped endpoint without a company selector.'
            );
        }

        return $this->companyId;
    }
}