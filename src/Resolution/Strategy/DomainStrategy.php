<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Support\TenantIdSanitizer;

final class DomainStrategy implements TenantResolverStrategy
{
    /** @var array<string, string> */
    private readonly array $domainToTenant;

    /**
     * @param array<string, string> $domainToTenant Exact host => tenant ID map
     */
    public function __construct(array $domainToTenant)
    {
        $normalized = [];

        foreach ($domainToTenant as $domain => $tenantId) {
            $domain = $this->normalizeHost($domain);
            $tenantId = TenantIdSanitizer::sanitize($tenantId);

            if ($domain === null || $tenantId === null) {
                continue;
            }

            $normalized[$domain] = $tenantId;
        }

        $this->domainToTenant = $normalized;
    }

    public function resolve(Request $request): ?TenantContext
    {
        $host = $request->getServer('HTTP_HOST');

        if ($host === '') {
            $host = $request->getServer('SERVER_NAME');
        }

        if ($host === '') {
            $host = $request->getServer('host');
        }

        if ($host === '') {
            $host = $request->getServer('server_name');
        }

        if ($host === '') {
            $host = (string) ($request->getHeader('host') ?? '');
        }

        $host = $this->normalizeHost($host);

        if ($host === null) {
            return null;
        }

        $tenantId = $this->domainToTenant[$host] ?? null;

        if ($tenantId === null) {
            return null;
        }

        return TenantContext::fromResolution($tenantId, 'domain', $host);
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        if ($host[0] === '[') {
            $endBracketPos = strpos($host, ']');

            if ($endBracketPos === false) {
                return null;
            }

            $host = substr($host, 0, $endBracketPos + 1);
        } else {
            $host = explode(':', $host, 2)[0];
        }

        return $host === '' ? null : $host;
    }
}
