<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Domain\Model\Tenant;
use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;
use Semitexa\Tenancy\Application\Service\Resolver\TenantValidator;

final class TenantValidatorTest extends TestCase
{
    #[Test]
    public function returns_null_for_default_context(): void
    {
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects($this->never())->method('findActive');

        $validator = new TenantValidator($repository);

        $this->assertNull($validator->validate(TenantContext::default()));
    }

    #[Test]
    public function returns_tenant_when_active(): void
    {
        $tenant = new Tenant('acme', 'Acme Corp', TenantStatus::Active);

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findActive')
            ->with('acme')
            ->willReturn($tenant);

        $validator = new TenantValidator($repository);
        $context = TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID');

        $result = $validator->validate($context);

        $this->assertSame($tenant, $result);
    }

    #[Test]
    public function returns_null_when_tenant_not_found(): void
    {
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findActive')
            ->with('unknown')
            ->willReturn(null);

        $validator = new TenantValidator($repository);
        $context = TenantContext::fromResolution('unknown', 'header', 'X-Tenant-ID');

        $this->assertNull($validator->validate($context));
    }

    #[Test]
    public function returns_null_when_tenant_is_suspended(): void
    {
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findActive')
            ->with('suspended-tenant')
            ->willReturn(null);

        $validator = new TenantValidator($repository);
        $context = TenantContext::fromResolution('suspended-tenant', 'header', 'X-Tenant-ID');

        $this->assertNull($validator->validate($context));
    }
}
