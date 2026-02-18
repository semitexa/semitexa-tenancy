<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

final class SubdomainStrategy implements TenantResolverStrategy
{
    public function __construct(
        private readonly string $baseDomain,
    ) {}

    public function resolve(Request $request): ?TenantContext
    {
        $host = $request->getServer('HTTP_HOST');

        if ($host === '') {
            $host = $request->getServer('SERVER_NAME');
        }

        if ($host === '') {
            return null;
        }

        // Strip port
        $host = strtolower(explode(':', $host)[0]);

        if ($host === '' || $host === $this->baseDomain) {
            return null;
        }

        // Host must end with .baseDomain
        $suffix = '.' . $this->baseDomain;

        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        // Extract subdomain
        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        $tenantId = preg_replace('/[^a-zA-Z0-9_-]/', '', $subdomain);

        if ($tenantId === '') {
            return null;
        }

        return new TenantContext($tenantId, 'subdomain', $host);
    }
}
