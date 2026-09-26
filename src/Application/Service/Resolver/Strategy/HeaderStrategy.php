<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\Resolver\Strategy;

use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Application\Service\TenantIdSanitizer;

/**
 * Tenant from a request header (X-Tenant-ID by default) — honoured only when
 * the request comes from a trusted proxy: loopback, or a peer listed in
 * TRUSTED_PROXIES, the same rule as X-Forwarded-Proto.
 *
 * A header is something any client can send. Trusted from anyone, it let an
 * anonymous caller pick whichever tenant it liked; the header strategy is for
 * a gateway in front of the app that authenticates the caller and sets (or
 * overwrites) the header itself.
 */
final class HeaderStrategy implements TenantResolverStrategyInterface
{
    public function __construct(
        private readonly string $headerName = 'X-Tenant-ID',
        private readonly int $maxLength = 64,
    ) {}

    public function resolve(Request $request): ?TenantContext
    {
        if (!$request->isTrustedForwardedRequest()) {
            return null;
        }

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
