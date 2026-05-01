<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Identification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Domain\Model\Tenant;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;

final class TenantTest extends TestCase
{
    #[Test]
    public function constructor_creates_tenant(): void
    {
        $tenant = new Tenant('t-1', 'Acme Corp', TenantStatus::Active, ['key' => 'value']);

        $this->assertSame('t-1', $tenant->id);
        $this->assertSame('Acme Corp', $tenant->name);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame(['key' => 'value'], $tenant->config);
    }

    #[Test]
    public function constructor_defaults_config_to_empty_array(): void
    {
        $tenant = new Tenant('t-1', 'Acme Corp', TenantStatus::Active);

        $this->assertSame([], $tenant->config);
    }

    #[Test]
    public function static_create_normalizes_status_from_string(): void
    {
        $tenant = Tenant::create('t-1', 'Acme Corp', 'active');

        $this->assertSame(TenantStatus::Active, $tenant->status);
    }

    #[Test]
    public function static_create_defaults_status_to_active(): void
    {
        $tenant = Tenant::create('t-1', 'Acme Corp');

        $this->assertSame(TenantStatus::Active, $tenant->status);
    }

    #[Test]
    public function static_create_passes_through_enum_status(): void
    {
        $tenant = Tenant::create('t-1', 'Acme Corp', TenantStatus::Suspended);

        $this->assertSame(TenantStatus::Suspended, $tenant->status);
    }

    #[Test]
    public function tenant_status_normalize_from_string(): void
    {
        $this->assertSame(TenantStatus::Active, TenantStatus::normalize('active'));
        $this->assertSame(TenantStatus::Suspended, TenantStatus::normalize('suspended'));
        $this->assertSame(TenantStatus::Deleted, TenantStatus::normalize('deleted'));
    }

    #[Test]
    public function tenant_status_normalize_from_enum(): void
    {
        $this->assertSame(TenantStatus::Active, TenantStatus::normalize(TenantStatus::Active));
    }

    #[Test]
    public function tenant_status_normalize_from_null_defaults_to_active(): void
    {
        $this->assertSame(TenantStatus::Active, TenantStatus::normalize(null));
    }

    #[Test]
    public function tenant_status_normalize_trims_and_lowercases(): void
    {
        $this->assertSame(TenantStatus::Active, TenantStatus::normalize('  ACTIVE  '));
    }

    #[Test]
    public function tenant_status_normalize_throws_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);

        TenantStatus::normalize('invalid');
    }
}
