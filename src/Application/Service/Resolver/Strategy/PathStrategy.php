<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\Resolver\Strategy;

use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Application\Service\TenantIdSanitizer;

final class PathStrategy implements TenantResolverStrategyInterface
{
    /** @param string[] $prefixes Segments that ARE tenant/locale identifiers (allowlist mode) */
    /** @param string[] $excludedPrefixes Segments to never treat as tenant (blocklist); when non-empty, exact match excludes */
    public function __construct(
        private readonly array $prefixes = [],
        private readonly array $excludedPrefixes = [],
    ) {}

    /**
     * Create a PathStrategy that only resolves the given segments as tenant identifiers.
     */
    public static function allowOnly(array $prefixes): self
    {
        return new self(prefixes: $prefixes);
    }

    /**
     * Create a PathStrategy that resolves all segments except the given ones.
     */
    public static function excludeOnly(array $excludedPrefixes): self
    {
        return new self(excludedPrefixes: $excludedPrefixes);
    }

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

        $tenantId = TenantIdSanitizer::sanitize($firstSegment);

        if ($tenantId === null) {
            return null;
        }

        return TenantContext::fromResolution($tenantId, 'path', $firstSegment);
    }
}
