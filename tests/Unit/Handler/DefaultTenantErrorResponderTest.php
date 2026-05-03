<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Application\Service\DefaultTenantErrorResponder;

final class DefaultTenantErrorResponderTest extends TestCase
{
    #[Test]
    public function tenant_not_found_returns_404(): void
    {
        $responder = new DefaultTenantErrorResponder();
        $context = TenantContext::fromResolution('unknown', 'header');

        $response = $responder->tenantNotFound($context);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function tenant_required_returns_400(): void
    {
        $responder = new DefaultTenantErrorResponder();

        $response = $responder->tenantRequired();

        $this->assertSame(400, $response->getStatusCode());
    }
}
