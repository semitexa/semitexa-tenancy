<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

final class PathStrategy implements TenantResolverStrategy
{
    /** @param string[] $prefixes Segments that ARE tenant/locale identifiers (allowlist mode) */
    /** @param string[] $excludedPrefixes Segments to never treat as tenant (blocklist); when non-empty, exact match excludes */
    public function __construct(
        private readonly array $prefixes = [],
        private readonly array $excludedPrefixes = [],
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

        if ($this->excludedPrefixes !== [] && in_array($firstSegment, $this->excludedPrefixes, true)) {
            return null;
        }

        if ($this->prefixes !== [] && !in_array($firstSegment, $this->prefixes, true)) {
            return null;
        }

        $tenantId = preg_replace('/[^a-zA-Z0-9_-]/', '', $firstSegment);

        if ($tenantId === '') {
            return null;
        }

        return TenantContext::fromResolution($tenantId, 'path', $firstSegment);
    }
}
