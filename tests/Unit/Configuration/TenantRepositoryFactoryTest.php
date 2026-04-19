<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Configuration\TenancyConfiguration;
use Semitexa\Tenancy\Configuration\TenantRepositoryFactory;
use Semitexa\Tenancy\Identification\ConfigTenantRepository;

final class TenantRepositoryFactoryTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalTenantEnv = [];

    protected function setUp(): void
    {
        foreach (getenv() ?: [] as $key => $value) {
            if (str_starts_with((string) $key, 'TENANT_')) {
                $this->originalTenantEnv[(string) $key] = $value;
                putenv((string) $key);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalTenantEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }
            putenv($key . '=' . $value);
        }
        $this->originalTenantEnv = [];
    }

    #[Test]
    public function builds_repository_from_compact_config(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'header',
            headerName: '', baseDomain: '', pathExcluded: '', queryParam: '',
            tenantsCompact: 'acme:Acme Corp:active', defaultLocale: '', defaultEnvironment: '',
        );

        $factory = new TenantRepositoryFactory();
        $repo = $factory->build($config);

        $this->assertInstanceOf(ConfigTenantRepository::class, $repo);
        $this->assertNotNull($repo->findActive('acme'));
    }

    #[Test]
    public function builds_empty_repository(): void
    {
        $config = new TenancyConfiguration(
            enabled: true, requireTenant: false, strategy: 'header',
            headerName: '', baseDomain: '', pathExcluded: '', queryParam: '',
            tenantsCompact: '', defaultLocale: '', defaultEnvironment: '',
        );

        $factory = new TenantRepositoryFactory();
        $repo = $factory->build($config);

        $this->assertInstanceOf(ConfigTenantRepository::class, $repo);
        $this->assertSame([], $repo->findAll());
    }
}
