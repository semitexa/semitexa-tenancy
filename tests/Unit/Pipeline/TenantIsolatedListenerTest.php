<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Pipeline;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Discovery\DiscoveredRoute;
use Semitexa\Core\Exception\AccessDeniedException;
use Semitexa\Core\Pipeline\RequestPipelineContext;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Attribute\TenantIsolated;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Pipeline\TenantIsolatedListener;

// Here, not in tests/Fixtures: this package's test namespace is not in the workspace
// autoload map (bench-crud-tests-not-autoloaded).
#[TenantIsolated]
class IsolatedPayload
{
}

final class GeneratedPayload extends IsolatedPayload
{
}

final class PlainPayload
{
}

#[TenantIsolated]
class IsolatedHandler
{
}

final class ChildOfIsolatedHandler extends IsolatedHandler
{
}

final class TenantIsolatedListenerTest extends TestCase
{
    #[Test]
    public function an_isolated_payload_with_no_tenant_is_refused(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->listener(null)->handle($this->context(new IsolatedPayload()));
    }

    #[Test]
    public function a_generated_subclass_of_it_is_refused_too(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->listener(null)->handle($this->context(new GeneratedPayload()));
    }

    #[Test]
    public function an_isolated_handler_with_no_tenant_is_refused(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->listener(null)->handle($this->context(new PlainPayload(), [['class' => IsolatedHandler::class]]));
    }

    #[Test]
    public function a_subclass_of_an_isolated_handler_is_refused_too(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->listener(null)->handle($this->context(new PlainPayload(), [['class' => ChildOfIsolatedHandler::class]]));
    }

    #[Test]
    public function the_default_tenant_is_no_tenant(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->listener(TenantContext::fromResolution('default', 'test'))->handle($this->context(new IsolatedPayload()));
    }

    #[Test]
    public function a_resolved_tenant_goes_through(): void
    {
        $this->listener(TenantContext::fromResolution('acme', 'test'))->handle($this->context(new IsolatedPayload()));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_route_that_says_nothing_needs_no_tenant(): void
    {
        $this->listener(null)->handle($this->context(new PlainPayload()));

        $this->addToAssertionCount(1);
    }

    private function listener(?object $context): TenantIsolatedListener
    {
        $store = $this->createMock(TenantContextStoreInterface::class);
        $store->method('tryGet')->willReturn($context);
        $listener = new TenantIsolatedListener();
        (new \ReflectionProperty(TenantIsolatedListener::class, 'tenants'))->setValue($listener, $store);

        return $listener;
    }

    /** @param list<array<string, string>> $handlers */
    private function context(object $payload, array $handlers = []): RequestPipelineContext
    {
        $route = (new \ReflectionClass(DiscoveredRoute::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DiscoveredRoute::class, 'handlers'))->setValue($route, $handlers);

        return new RequestPipelineContext(
            requestDto: $payload,
            route: $route,
            request: new Request(method: 'GET', uri: '/isolated', headers: [], query: [], post: [], server: [], cookies: []),
        );
    }
}
