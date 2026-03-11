<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Support\TenantIdSanitizer;

final class QueryParamStrategy implements TenantResolverStrategy
{
    public function __construct(
        private readonly string $paramName = 'tenant',
        private readonly int $maxLength = 64,
    ) {}

    public function resolve(Request $request): ?TenantContext
    {
        $value = $request->getQuery($this->paramName);

        if ($value === '') {
            return null;
        }

        $tenantId = TenantIdSanitizer::sanitize($value, $this->maxLength);

        if ($tenantId === null) {
            return null;
        }

        return TenantContext::fromResolution($tenantId, 'query', $value);
    }
}
