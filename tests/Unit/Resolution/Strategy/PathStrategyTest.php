<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Resolution\Strategy\PathStrategy;

final class PathStrategyTest extends TestCase
{
    #[Test]
    public function resolves_tenant_from_first_path_segment(): void
    {
        $strategy = new PathStrategy();
        $request = $this->makeRequest('/acme/products');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('path', $context->strategy);
        $this->assertSame('acme', $context->source);
    }

    #[Test]
    public function resolves_tenant_from_single_segment_path(): void
    {
        $strategy = new PathStrategy();
        $request = $this->makeRequest('/acme');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }

    #[Test]
    public function returns_null_for_root_path(): void
    {
        $strategy = new PathStrategy();
        $request = $this->makeRequest('/');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function returns_null_for_excluded_prefix(): void
    {
        $strategy = new PathStrategy(excludedPrefixes: ['api', 'admin', 'health']);
        $request = $this->makeRequest('/api/users');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function resolves_non_excluded_prefix(): void
    {
        $strategy = new PathStrategy(excludedPrefixes: ['api', 'admin']);
        $request = $this->makeRequest('/acme/products');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }

    #[Test]
    public function sanitizes_path_segment(): void
    {
        $strategy = new PathStrategy();
        $request = $this->makeRequest('/ac!me@corp/products');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acmecorp', $context->tenantId);
    }

    #[Test]
    public function returns_null_when_sanitized_segment_is_empty(): void
    {
        $strategy = new PathStrategy();
        $request = $this->makeRequest('/!!!/products');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function allows_hyphens_and_underscores(): void
    {
        $strategy = new PathStrategy();
        $request = $this->makeRequest('/my-tenant_01/dashboard');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('my-tenant_01', $context->tenantId);
    }

    #[Test]
    public function excluded_prefixes_are_exact_match(): void
    {
        $strategy = new PathStrategy(excludedPrefixes: ['api']);
        $request = $this->makeRequest('/api-v2/users');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('api-v2', $context->tenantId);
    }

    private function makeRequest(string $path): Request
    {
        return new Request(
            method: 'GET',
            uri: $path,
            headers: [],
            query: [],
            post: [],
            server: [],
            cookies: [],
        );
    }
}
