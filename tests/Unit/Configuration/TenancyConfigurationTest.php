<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Configuration\TenancyConfiguration;

final class TenancyConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TENANCY_ENABLED');
        putenv('TENANCY_REQUIRED');
        putenv('TENANCY_STRATEGY');
        putenv('TENANCY_HEADER_NAME');
        putenv('TENANCY_BASE_DOMAIN');
        putenv('TENANCY_PATH_EXCLUDED');
        putenv('TENANCY_QUERY_PARAM');
        putenv('TENANTS');
        putenv('TENANCY_DEFAULT_LOCALE');
        putenv('TENANCY_DEFAULT_ENVIRONMENT');
    }

    #[Test]
    public function from_env_reads_defaults(): void
    {
        $config = TenancyConfiguration::fromEnv();

        $this->assertFalse($config->enabled);
        $this->assertFalse($config->requireTenant);
        $this->assertSame('header', $config->strategy);
        $this->assertSame('X-Tenant-ID', $config->headerName);
        $this->assertSame('tenant', $config->queryParam);
    }

    #[Test]
    public function from_env_reads_set_values(): void
    {
        putenv('TENANCY_ENABLED=true');
        putenv('TENANCY_REQUIRED=true');
        putenv('TENANCY_STRATEGY=subdomain,path');
        putenv('TENANCY_HEADER_NAME=X-Custom');
        putenv('TENANCY_BASE_DOMAIN=example.com');

        $config = TenancyConfiguration::fromEnv();

        $this->assertTrue($config->enabled);
        $this->assertTrue($config->requireTenant);
        $this->assertSame('subdomain,path', $config->strategy);
        $this->assertSame('X-Custom', $config->headerName);
        $this->assertSame('example.com', $config->baseDomain);
    }

    #[Test]
    public function get_strategy_names_splits_comma_separated(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'header, subdomain, path',
            headerName: '', baseDomain: '', pathExcluded: '', queryParam: '',
            tenantsCompact: '', defaultLocale: '', defaultEnvironment: '',
        );

        $this->assertSame(['header', 'subdomain', 'path'], $config->getStrategyNames());
    }

    #[Test]
    public function get_excluded_prefixes_splits_comma_separated(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'path',
            headerName: '', baseDomain: '', pathExcluded: 'api, admin, health',
            queryParam: '', tenantsCompact: '', defaultLocale: '', defaultEnvironment: '',
        );

        $this->assertSame(['api', 'admin', 'health'], $config->getExcludedPrefixes());
    }
}
