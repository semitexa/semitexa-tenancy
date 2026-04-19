<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Configuration\TenancyConfiguration;

final class TenancyConfigurationTest extends TestCase
{
    private const TENANCY_ENV_VARS = [
        'TENANCY_ENABLED',
        'TENANCY_REQUIRED',
        'TENANCY_STRATEGY',
        'TENANCY_HEADER_NAME',
        'TENANCY_BASE_DOMAIN',
        'TENANCY_PATH_EXCLUDED',
        'TENANCY_QUERY_PARAM',
        'TENANTS',
        'TENANCY_DEFAULT_LOCALE',
        'TENANCY_DEFAULT_ENVIRONMENT',
    ];

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        foreach (self::TENANCY_ENV_VARS as $key) {
            $this->originalEnv[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }
            putenv($key . '=' . $value);
        }
        $this->originalEnv = [];
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
