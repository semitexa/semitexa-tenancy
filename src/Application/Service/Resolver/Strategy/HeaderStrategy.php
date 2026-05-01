<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\Resolver\Strategy;

use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

use Semitexa\Tenancy\Domain\Model\Tenant;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Application\Service\TenantIdSanitizer;

final class HeaderStrategy implements TenantResolverStrategyInterface
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
