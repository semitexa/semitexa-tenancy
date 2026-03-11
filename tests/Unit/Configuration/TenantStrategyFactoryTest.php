<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Configuration\TenancyConfiguration;
use Semitexa\Tenancy\Configuration\TenantStrategyFactory;
use Semitexa\Tenancy\Resolution\TenantResolverChain;

final class TenantStrategyFactoryTest extends TestCase
{
    #[Test]
    public function builds_resolver_chain_from_config(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'header',
            headerName: 'X-Tenant-ID', baseDomain: '', pathExcluded: '',
            queryParam: 'tenant', tenantsCompact: '', defaultLocale: '', defaultEnvironment: '',
        );

        $factory = new TenantStrategyFactory();
        $resolver = $factory->build($config);

        $this->assertInstanceOf(TenantResolverChain::class, $resolver);
    }

    #[Test]
    public function skips_subdomain_when_no_base_domain(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'subdomain',
            headerName: '', baseDomain: '', pathExcluded: '',
            queryParam: '', tenantsCompact: '', defaultLocale: '', defaultEnvironment: '',
        );

        $factory = new TenantStrategyFactory();
        $resolver = $factory->build($config);

        $this->assertInstanceOf(TenantResolverChain::class, $resolver);
    }

    #[Test]
    public function builds_multiple_strategies(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'header,path,query',
            headerName: 'X-Tenant-ID', baseDomain: '', pathExcluded: 'api',
            queryParam: 'tenant', tenantsCompact: '', defaultLocale: '', defaultEnvironment: '',
        );

        $factory = new TenantStrategyFactory();
        $resolver = $factory->build($config);

        $this->assertInstanceOf(TenantResolverChain::class, $resolver);
    }
}
