<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Identification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Application\Service\ConfigTenantRepository;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;

final class ConfigTenantRepositoryTest extends TestCase
{
    #[Test]
    public function parses_compact_format(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp:active,globex:Globex Inc:suspended',
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame('acme', $acme->id);
        $this->assertSame('Acme Corp', $acme->name);
        $this->assertSame(TenantStatus::Active, $acme->status);

        $globex = $repo->find('globex');
        $this->assertNotNull($globex);
        $this->assertSame('Globex Inc', $globex->name);
        $this->assertSame(TenantStatus::Suspended, $globex->status);
    }

    #[Test]
    public function compact_format_defaults_status_to_active(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp',
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame(TenantStatus::Active, $acme->status);
    }

    #[Test]
    public function compact_format_defaults_name_to_id(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme',
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame('acme', $acme->name);
    }

    #[Test]
    public function compact_format_skips_empty_entries(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme Corp:active,,  ,globex:Globex:active',
        ]);

        $this->assertCount(2, $repo->findAll());
    }

    #[Test]
    public function compact_format_trims_whitespace(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => ' acme : Acme Corp : active ',
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame('Acme Corp', $acme->name);
    }

    #[Test]
    public function parses_detailed_format(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants' => [
                'acme' => [
                    'name' => 'Acme Corp',
                    'status' => 'active',
                    'config' => ['db' => 'acme_db'],
                ],
                'globex' => [
                    'name' => 'Globex Inc',
                    'status' => 'suspended',
                ],
            ],
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame('Acme Corp', $acme->name);
        $this->assertSame(TenantStatus::Active, $acme->status);
        $this->assertSame(['db' => 'acme_db'], $acme->config);

        $globex = $repo->find('globex');
        $this->assertNotNull($globex);
        $this->assertSame(TenantStatus::Suspended, $globex->status);
        $this->assertSame([], $globex->config);
    }

    #[Test]
    public function detailed_format_defaults_name_to_id(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants' => [
                'acme' => ['status' => 'active'],
            ],
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame('acme', $acme->name);
    }

    #[Test]
    public function find_returns_null_for_unknown_id(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:active',
        ]);

        $this->assertNull($repo->find('unknown'));
    }

    #[Test]
    public function exists_returns_true_for_known_id(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:active',
        ]);

        $this->assertTrue($repo->exists('acme'));
        $this->assertFalse($repo->exists('unknown'));
    }

    #[Test]
    public function find_active_returns_tenant_when_active(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:active',
        ]);

        $this->assertNotNull($repo->findActive('acme'));
    }

    #[Test]
    public function find_active_returns_null_when_suspended(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:suspended',
        ]);

        $this->assertNull($repo->findActive('acme'));
    }

    #[Test]
    public function find_active_returns_null_when_deleted(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:deleted',
        ]);

        $this->assertNull($repo->findActive('acme'));
    }

    #[Test]
    public function find_active_returns_null_for_unknown(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:active',
        ]);

        $this->assertNull($repo->findActive('unknown'));
    }

    #[Test]
    public function find_all_returns_all_tenants(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Acme:active,globex:Globex:suspended',
        ]);

        $all = $repo->findAll();
        $this->assertCount(2, $all);
    }

    #[Test]
    public function empty_config_produces_empty_repository(): void
    {
        $repo = new ConfigTenantRepository([]);

        $this->assertCount(0, $repo->findAll());
        $this->assertNull($repo->find('anything'));
    }

    #[Test]
    public function empty_compact_string_produces_empty_repository(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => '',
        ]);

        $this->assertCount(0, $repo->findAll());
    }

    #[Test]
    public function detailed_format_overrides_compact_for_same_id(): void
    {
        $repo = new ConfigTenantRepository([
            'tenants_compact' => 'acme:Compact Name:active',
            'tenants' => [
                'acme' => ['name' => 'Detailed Name', 'status' => 'suspended'],
            ],
        ]);

        $acme = $repo->find('acme');
        $this->assertNotNull($acme);
        $this->assertSame('Detailed Name', $acme->name);
        $this->assertSame(TenantStatus::Suspended, $acme->status);
    }
}
