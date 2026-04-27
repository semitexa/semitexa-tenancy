<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Support\TenantIdSanitizer;

final class SubdomainStrategy implements TenantResolverStrategy
{
    public function __construct(
        private readonly string $baseDomain,
    ) {}

    public function resolve(Request $request): ?TenantContext
    {
        // Read the host from the request's Host header. Under Swoole the host header
        // is in $request->headers['host'] and never in $request->server['HTTP_HOST'],
        // so reading from getServer() would always miss in real traffic.
        // Request::getHost() trims, strips port, lower-cases, and rejects malformed hosts.
        $host = $request->getHost();

        // Legacy fallback for hand-built requests (CLI, tests) that may populate
        // HTTP_HOST or either SERVER_NAME/server_name on the server array.
        if ($host === '') {
            $serverName = trim($request->getServer('HTTP_HOST'));
            if ($serverName === '') {
                $serverName = trim($request->getServer('SERVER_NAME'));
            }
            if ($serverName === '') {
                $serverName = trim($request->getServer('server_name'));
            }

            if ($serverName !== '') {
                $host = strtolower(explode(':', $serverName)[0]);
            }
        }

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

        $tenantId = TenantIdSanitizer::sanitize($subdomain);

        if ($tenantId === null) {
            return null;
        }

        return TenantContext::fromResolution($tenantId, 'subdomain', $host);
    }
}
