<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

final class PathStrategy implements TenantResolverStrategy
{
    /** @param string[] $prefixes Segments that ARE tenant/locale identifiers (allowlist mode) */
    public function __construct(
        private readonly array $prefixes = [],
    ) {}

    public function resolve(Request $request): ?TenantContext
    {
        $path = ltrim($request->getPath(), '/');

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path, 2);
        $firstSegment = $segments[0];

        if ($firstSegment === '') {
            return null;
        }

        if ($this->prefixes !== [] && !in_array($firstSegment, $this->prefixes, true)) {
            return null;
        }

        $tenantId = preg_replace('/[^a-zA-Z0-9_-]/', '', $firstSegment);

        if ($tenantId === '') {
            return null;
        }

        return new TenantContext($tenantId, 'path', $firstSegment);
    }
}
