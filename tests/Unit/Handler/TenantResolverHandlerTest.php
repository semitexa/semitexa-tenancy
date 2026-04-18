<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Event\TenantNotFound;
use Semitexa\Tenancy\Event\TenantResolved;
use Semitexa\Tenancy\Handler\TenantResolverHandler;
use Semitexa\Tenancy\Identification\Tenant;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;
use Semitexa\Tenancy\Identification\TenantStatus;
use Semitexa\Tenancy\Resolution\TenantResolverInterface;

final class TenantResolverHandlerTest extends TestCase
{
    private TenantContextStore $store;

    protected function setUp(): void
    {
        $this->store = new TenantContextStore();
        $this->store->clear();
    }

    protected function tearDown(): void
    {
        $this->store->clear();
    }

    #[Test]
    public function resolves_and_stores_active_tenant(): void
    {
        $context = TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID');
        $tenant = new Tenant('acme', 'Acme Corp', TenantStatus::Active);

        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($context);

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findActive')->with('acme')->willReturn($tenant);

        $handler = new TenantResolverHandler($resolver, $repo, $this->store);
        $response = $handler->handle($this->makeRequest());

        $this->assertNull($response);
        $stored = CoroutineContextStore::get();
        $this->assertNotNull($stored);
        $this->assertSame('acme', $stored->tenantId);
    }

    #[Test]
    public function returns_404_when_tenant_not_found(): void
    {
        $context = TenantContext::fromResolution('unknown', 'header', 'X-Tenant-ID');

        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($context);

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findActive')->with('unknown')->willReturn(null);

        $handler = new TenantResolverHandler($resolver, $repo, $this->store);
        $response = $handler->handle($this->makeRequest());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function returns_404_when_tenant_is_suspended(): void
    {
        $context = TenantContext::fromResolution('suspended', 'header', 'X-Tenant-ID');

        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($context);

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findActive')->with('suspended')->willReturn(null);

        $handler = new TenantResolverHandler($resolver, $repo, $this->store);
        $response = $handler->handle($this->makeRequest());

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function allows_default_context_when_not_required(): void
    {
        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn(TenantContext::default());

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->expects($this->never())->method('findActive');

        $handler = new TenantResolverHandler($resolver, $repo, $this->store, requireTenant: false);
        $response = $handler->handle($this->makeRequest());

        $this->assertNull($response);
        $stored = CoroutineContextStore::get();
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isDefault());
    }

    #[Test]
    public function returns_400_when_tenant_required_but_default(): void
    {
        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn(TenantContext::default());

        $repo = $this->createMock(TenantRepositoryInterface::class);

        $handler = new TenantResolverHandler($resolver, $repo, $this->store, requireTenant: true);
        $response = $handler->handle($this->makeRequest());

        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function dispatches_tenant_resolved_event(): void
    {
        $context = TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID');
        $tenant = new Tenant('acme', 'Acme Corp', TenantStatus::Active);

        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($context);

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findActive')->willReturn($tenant);

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $event) use ($tenant): bool {
                return $event instanceof TenantResolved
                    && $event->context->tenantId === 'acme'
                    && $event->tenant === $tenant;
            }));

        $handler = new TenantResolverHandler($resolver, $repo, $this->store, $events);
        $handler->handle($this->makeRequest());
    }

    #[Test]
    public function dispatches_tenant_not_found_event(): void
    {
        $context = TenantContext::fromResolution('unknown', 'header', 'X-Tenant-ID');

        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($context);

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findActive')->willReturn(null);

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $event): bool {
                return $event instanceof TenantNotFound
                    && $event->context->tenantId === 'unknown';
            }));

        $handler = new TenantResolverHandler($resolver, $repo, $this->store, $events);
        $handler->handle($this->makeRequest());
    }

    #[Test]
    public function dispatches_tenant_resolved_for_default_context(): void
    {
        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn(TenantContext::default());

        $repo = $this->createMock(TenantRepositoryInterface::class);

        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $event): bool {
                return $event instanceof TenantResolved
                    && $event->context->isDefault()
                    && $event->tenant === null;
            }));

        $handler = new TenantResolverHandler($resolver, $repo, $this->store, $events);
        $handler->handle($this->makeRequest());
    }

    #[Test]
    public function works_without_event_dispatcher(): void
    {
        $context = TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID');
        $tenant = new Tenant('acme', 'Acme Corp', TenantStatus::Active);

        $resolver = $this->createMock(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($context);

        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findActive')->willReturn($tenant);

        $handler = new TenantResolverHandler($resolver, $repo, $this->store);
        $response = $handler->handle($this->makeRequest());

        $this->assertNull($response);
    }

    private function makeRequest(): Request
    {
        return new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            post: [],
            server: [],
            cookies: [],
        );
    }
}
