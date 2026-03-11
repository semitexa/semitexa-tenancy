<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\OrganizationValue;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;
use Semitexa\Tenancy\Strategy\LayerStrategyAdapter;

final class LayerStrategyAdapterTest extends TestCase
{
    #[Test]
    public function returns_layer_value_when_context_resolved(): void
    {
        $context = TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID');

        $resolver = $this->createMock(TenantResolverStrategy::class);
        $resolver->method('resolve')->willReturn($context);

        $adapter = new LayerStrategyAdapter(
            layer: new OrganizationLayer(),
            resolver: $resolver,
            valueFactory: fn(string $id) => new OrganizationValue($id),
        );

        $this->assertInstanceOf(OrganizationLayer::class, $adapter->layer());

        $result = $adapter->resolve($this->makeRequest());
        $this->assertInstanceOf(OrganizationValue::class, $result);
        $this->assertSame('acme', $result->rawValue());
    }

    #[Test]
    public function returns_null_when_no_context(): void
    {
        $resolver = $this->createMock(TenantResolverStrategy::class);
        $resolver->method('resolve')->willReturn(null);

        $adapter = new LayerStrategyAdapter(
            layer: new OrganizationLayer(),
            resolver: $resolver,
            valueFactory: fn(string $id) => new OrganizationValue($id),
        );

        $this->assertNull($adapter->resolve($this->makeRequest()));
    }

    #[Test]
    public function returns_null_for_default_context(): void
    {
        $resolver = $this->createMock(TenantResolverStrategy::class);
        $resolver->method('resolve')->willReturn(TenantContext::default());

        $adapter = new LayerStrategyAdapter(
            layer: new OrganizationLayer(),
            resolver: $resolver,
            valueFactory: fn(string $id) => new OrganizationValue($id),
        );

        $this->assertNull($adapter->resolve($this->makeRequest()));
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
