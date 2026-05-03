<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\HeaderStrategy;

final class HeaderStrategyTest extends TestCase
{
    #[Test]
    public function resolves_tenant_from_default_header(): void
    {
        $strategy = new HeaderStrategy();
        $request = $this->makeRequest(headers: ['X-Tenant-ID' => 'acme']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('header', $context->strategy);
        $this->assertSame('acme', $context->source);
    }

    #[Test]
    public function resolves_tenant_from_custom_header(): void
    {
        $strategy = new HeaderStrategy(headerName: 'X-Organization');
        $request = $this->makeRequest(headers: ['X-Organization' => 'globex']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('globex', $context->tenantId);
    }

    #[Test]
    public function returns_null_when_header_missing(): void
    {
        $strategy = new HeaderStrategy();
        $request = $this->makeRequest();

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function returns_null_when_header_empty(): void
    {
        $strategy = new HeaderStrategy();
        $request = $this->makeRequest(headers: ['X-Tenant-ID' => '']);

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function sanitizes_special_characters(): void
    {
        $strategy = new HeaderStrategy();
        $request = $this->makeRequest(headers: ['X-Tenant-ID' => 'acme<script>alert(1)</script>']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acmescriptalert1script', $context->tenantId);
    }

    #[Test]
    public function returns_null_when_sanitized_value_is_empty(): void
    {
        $strategy = new HeaderStrategy();
        $request = $this->makeRequest(headers: ['X-Tenant-ID' => '!!!']);

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function truncates_to_max_length(): void
    {
        $strategy = new HeaderStrategy(maxLength: 5);
        $request = $this->makeRequest(headers: ['X-Tenant-ID' => 'very-long-tenant-id']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('very-', $context->tenantId);
    }

    #[Test]
    public function allows_hyphens_and_underscores(): void
    {
        $strategy = new HeaderStrategy();
        $request = $this->makeRequest(headers: ['X-Tenant-ID' => 'my-tenant_01']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('my-tenant_01', $context->tenantId);
    }

    private function makeRequest(array $headers = []): Request
    {
        return new Request(
            method: 'GET',
            uri: '/',
            headers: $headers,
            query: [],
            post: [],
            server: [],
            cookies: [],
        );
    }
}
