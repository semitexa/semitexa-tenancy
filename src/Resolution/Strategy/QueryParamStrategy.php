<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

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

        $tenantId = $this->sanitize($value);

        if ($tenantId === null) {
            return null;
        }

        return new TenantContext($tenantId, 'query', $value);
    }

    private function sanitize(string $value): ?string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);

        if ($clean === '') {
            return null;
        }

        return substr($clean, 0, $this->maxLength);
    }
}
