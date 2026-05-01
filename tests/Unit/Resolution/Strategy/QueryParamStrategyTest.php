<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\QueryParamStrategy;

final class QueryParamStrategyTest extends TestCase
{
    #[Test]
    public function resolves_tenant_from_default_param(): void
    {
        $strategy = new QueryParamStrategy();
        $request = $this->makeRequest(query: ['tenant' => 'acme']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('query', $context->strategy);
        $this->assertSame('acme', $context->source);
    }

    #[Test]
    public function resolves_tenant_from_custom_param(): void
    {
        $strategy = new QueryParamStrategy(paramName: 'org');
        $request = $this->makeRequest(query: ['org' => 'globex']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('globex', $context->tenantId);
    }

    #[Test]
    public function returns_null_when_param_missing(): void
    {
        $strategy = new QueryParamStrategy();
        $request = $this->makeRequest(query: []);

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function returns_null_when_param_empty(): void
    {
        $strategy = new QueryParamStrategy();
        $request = $this->makeRequest(query: ['tenant' => '']);

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function sanitizes_special_characters(): void
    {
        $strategy = new QueryParamStrategy();
        $request = $this->makeRequest(query: ['tenant' => 'acme<>corp']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acmecorp', $context->tenantId);
    }

    #[Test]
    public function returns_null_when_sanitized_value_is_empty(): void
    {
        $strategy = new QueryParamStrategy();
        $request = $this->makeRequest(query: ['tenant' => '!!!']);

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function truncates_to_max_length(): void
    {
        $strategy = new QueryParamStrategy(maxLength: 4);
        $request = $this->makeRequest(query: ['tenant' => 'very-long-id']);

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('very', $context->tenantId);
    }

    private function makeRequest(array $query): Request
    {
        return new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: $query,
            post: [],
            server: [],
            cookies: [],
        );
    }
}
