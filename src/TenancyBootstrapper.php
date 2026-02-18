<?php

declare(strict_types=1);

namespace Semitexa\Tenancy;

use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Tenancy\Handler\TenantResolverHandler;
use Semitexa\Tenancy\Identification\ConfigTenantRepository;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;
use Semitexa\Tenancy\Resolution\TenantResolverChain;
use Semitexa\Tenancy\Resolution\TenantResolverInterface;
use Semitexa\Tenancy\Resolution\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Resolution\Strategy\PathStrategy;
use Semitexa\Tenancy\Resolution\Strategy\QueryParamStrategy;
use Semitexa\Tenancy\Resolution\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;

/**
 * Builds the tenancy handler from environment configuration.
 *
 * Reads TENANCY_* and TENANTS* environment variables and constructs
 * the full resolution chain, repository, and handler.
 */
final class TenancyBootstrapper
{
    private TenantResolverHandler $handler;
    private TenantResolverInterface $resolver;
    private TenantRepositoryInterface $repository;
    private bool $enabled;

    public function __construct(?EventDispatcherInterface $events = null)
    {
        $this->enabled = self::env('TENANCY_ENABLED', 'false') === 'true';

        $this->repository = $this->buildRepository();
        $this->resolver = $this->buildResolver();
        $this->handler = new TenantResolverHandler(
            resolver: $this->resolver,
            tenants: $this->repository,
            events: $events,
            requireTenant: self::env('TENANCY_REQUIRED', 'false') === 'true',
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getHandler(): TenantResolverHandler
    {
        return $this->handler;
    }

    public function getResolver(): TenantResolverInterface
    {
        return $this->resolver;
    }

    public function getRepository(): TenantRepositoryInterface
    {
        return $this->repository;
    }

    private function buildResolver(): TenantResolverInterface
    {
        $strategyNames = array_filter(
            array_map('trim', explode(',', self::env('TENANCY_STRATEGY', 'header'))),
        );

        $strategies = [];

        foreach ($strategyNames as $name) {
            $strategy = $this->buildStrategy($name);

            if ($strategy !== null) {
                $strategies[] = $strategy;
            }
        }

        return new TenantResolverChain($strategies);
    }

    private function buildStrategy(string $name): ?TenantResolverStrategy
    {
        return match ($name) {
            'header' => new HeaderStrategy(
                headerName: self::env('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
            ),
            'subdomain' => $this->buildSubdomainStrategy(),
            'path' => new PathStrategy(
                excludedPrefixes: array_filter(
                    array_map('trim', explode(',', self::env('TENANCY_PATH_EXCLUDED', ''))),
                ),
            ),
            'query' => new QueryParamStrategy(
                paramName: self::env('TENANCY_QUERY_PARAM', 'tenant'),
            ),
            default => null,
        };
    }

    private function buildSubdomainStrategy(): ?SubdomainStrategy
    {
        $baseDomain = self::env('TENANCY_BASE_DOMAIN', '');

        if ($baseDomain === '') {
            return null;
        }

        return new SubdomainStrategy(baseDomain: $baseDomain);
    }

    private function buildRepository(): TenantRepositoryInterface
    {
        $config = [];

        $compact = self::env('TENANTS', '');
        if ($compact !== '') {
            $config['tenants_compact'] = $compact;
        }

        // Scan for TENANT_{ID}_NAME / TENANT_{ID}_STATUS pattern
        $detailed = $this->scanDetailedTenants();
        if ($detailed !== []) {
            $config['tenants'] = $detailed;
        }

        return new ConfigTenantRepository($config);
    }

    /**
     * Scan environment for TENANT_{ID}_NAME / TENANT_{ID}_STATUS patterns.
     *
     * @return array<string, array{name?: string, status?: string}>
     */
    private function scanDetailedTenants(): array
    {
        $tenants = [];

        // Check $_ENV, $_SERVER, and .env file for TENANT_*_NAME keys
        $sources = array_merge(
            getenv() ?: [],
            $_ENV,
            $_SERVER,
        );

        foreach ($sources as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'TENANT_') || $key === 'TENANT_STRATEGY' || $key === 'TENANT_HEADER' || $key === 'TENANT_DEFAULT') {
                continue;
            }

            // Match TENANT_{ID}_NAME or TENANT_{ID}_STATUS
            if (preg_match('/^TENANT_([A-Z0-9_]+?)_(NAME|STATUS)$/', $key, $matches)) {
                $id = strtolower($matches[1]);
                $field = strtolower($matches[2]);
                $tenants[$id][$field] = (string) $value;
            }
        }

        return $tenants;
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }
}
