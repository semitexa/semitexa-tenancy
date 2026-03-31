<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Resolution\Strategy\DomainStrategy;

final class DomainStrategyTest extends TestCase
{
    #[Test]
    public function resolves_tenant_from_exact_domain(): void
    {
        $strategy = new DomainStrategy([
            'semitexa.test' => 'semitexa',
        ]);

        $context = $strategy->resolve($this->makeRequest('semitexa.test'));

        $this->assertNotNull($context);
        $this->assertSame('semitexa', $context->tenantId);
        $this->assertSame('domain', $context->strategy);
        $this->assertSame('semitexa.test', $context->source);
    }

    #[Test]
    public function returns_null_for_unknown_domain(): void
    {
        $strategy = new DomainStrategy([
            'semitexa.test' => 'semitexa',
        ]);

        $this->assertNull($strategy->resolve($this->makeRequest('demo.semitexa.test')));
    }

    #[Test]
    public function strips_port_and_normalizes_case(): void
    {
        $strategy = new DomainStrategy([
            'demo.semitexa.test' => 'demo',
        ]);

        $context = $strategy->resolve($this->makeRequest('DEMO.SEMITEXA.TEST:8080'));

        $this->assertNotNull($context);
        $this->assertSame('demo', $context->tenantId);
        $this->assertSame('demo.semitexa.test', $context->source);
    }

    private function makeRequest(string $host): Request
    {
        return new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            post: [],
            server: ['HTTP_HOST' => $host],
            cookies: [],
        );
    }
}
