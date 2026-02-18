<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Resolution\Strategy\SubdomainStrategy;

final class SubdomainStrategyTest extends TestCase
{
    #[Test]
    public function resolves_tenant_from_subdomain(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'acme.example.com');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('subdomain', $context->strategy);
        $this->assertSame('acme.example.com', $context->source);
    }

    #[Test]
    public function returns_null_for_base_domain(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'example.com');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function returns_null_for_two_part_domain_bug_fix(): void
    {
        // This was Bug #1 — previously, 'example.com' would resolve 'example' as tenant
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'example.com');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function returns_null_for_unrelated_domain(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'other-site.org');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function returns_null_for_multi_level_subdomain(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'sub.acme.example.com');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function strips_port_from_host(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'acme.example.com:8080');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }

    #[Test]
    public function normalizes_host_to_lowercase(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'ACME.Example.COM');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }

    #[Test]
    public function returns_null_for_empty_host(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: '');

        $this->assertNull($strategy->resolve($request));
    }

    #[Test]
    public function falls_back_to_server_name(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            post: [],
            server: ['SERVER_NAME' => 'acme.example.com'],
            cookies: [],
        );

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }

    #[Test]
    public function sanitizes_subdomain(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: 'ac!me.example.com');

        $context = $strategy->resolve($request);

        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }

    #[Test]
    public function returns_null_when_sanitized_subdomain_is_empty(): void
    {
        $strategy = new SubdomainStrategy(baseDomain: 'example.com');
        $request = $this->makeRequest(host: '!!!.example.com');

        $this->assertNull($strategy->resolve($request));
    }

    private function makeRequest(string $host = ''): Request
    {
        return new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            post: [],
            server: $host !== '' ? ['HTTP_HOST' => $host] : [],
            cookies: [],
        );
    }
}
