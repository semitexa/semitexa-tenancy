<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Domain\Event\TenantNotFound;
use Semitexa\Tenancy\Domain\Event\TenantResolved;
use Semitexa\Tenancy\Domain\Event\TenantSwitched;
use Semitexa\Tenancy\Domain\Model\Tenant;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;

final class TenantEventTest extends TestCase
{
    #[Test]
    public function tenant_resolved_holds_context_and_tenant(): void
    {
        $context = TenantContext::fromResolution('acme', 'header');
        $tenant = new Tenant('acme', 'Acme Corp', TenantStatus::Active);

        $event = new TenantResolved($context, $tenant);

        $this->assertSame($context, $event->context);
        $this->assertSame($tenant, $event->tenant);
    }

    #[Test]
    public function tenant_resolved_tenant_is_nullable(): void
    {
        $context = TenantContext::default();

        $event = new TenantResolved($context);

        $this->assertNull($event->tenant);
    }

    #[Test]
    public function tenant_not_found_holds_context(): void
    {
        $context = TenantContext::fromResolution('unknown', 'header');

        $event = new TenantNotFound($context);

        $this->assertSame($context, $event->context);
    }

    #[Test]
    public function tenant_switched_holds_previous_and_current(): void
    {
        $previous = TenantContext::fromResolution('acme', 'cli');
        $current = TenantContext::fromResolution('globex', 'cli');

        $event = new TenantSwitched($previous, $current);

        $this->assertSame($previous, $event->previous);
        $this->assertSame($current, $event->current);
    }
}
