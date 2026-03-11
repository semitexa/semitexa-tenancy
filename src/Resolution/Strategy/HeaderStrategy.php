<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Support\TenantIdSanitizer;

final class HeaderStrategy implements TenantResolverStrategy
{
    public function __construct(
        private readonly string $headerName = 'X-Tenant-ID',
        private readonly int $maxLength = 64,
    ) {}

    public function resolve(Request $request): ?TenantContext
    {
        $value = $request->getHeader($this->headerName);

        if ($value === null || $value === '') {
            return null;
        }

        $tenantId = TenantIdSanitizer::sanitize($value, $this->maxLength);

        if ($tenantId === null) {
            return null;
        }

        return TenantContext::fromResolution($tenantId, 'header', $value);
    }
}
