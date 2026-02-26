<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Handler\TenantRequiredGuard;

final class TenantRequiredGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        CoroutineContextStore::clearFallback();
    }

    #[Test]
    public function returns_403_when_no_context_set(): void
    {
        $guard = new TenantRequiredGuard();

        $response = $guard->check();

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function returns_403_when_context_is_default(): void
    {
        CoroutineContextStore::setFallback(TenantContext::default());

        $guard = new TenantRequiredGuard();

        $response = $guard->check();

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function returns_null_when_tenant_is_present(): void
    {
        CoroutineContextStore::setFallback(TenantContext::fromResolution('acme', 'header'));

        $guard = new TenantRequiredGuard();

        $this->assertNull($guard->check());
    }
}
