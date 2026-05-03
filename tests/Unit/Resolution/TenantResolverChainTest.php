<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;
use Semitexa\Tenancy\Application\Service\Resolver\TenantResolverChain;

final class TenantResolverChainTest extends TestCase
{
    #[Test]
    public function returns_default_context_when_no_strategies(): void
    {
        $chain = new TenantResolverChain([]);
        $request = $this->makeRequest();

        $context = $chain->resolve($request);

        $this->assertTrue($context->isDefault());
    }

    #[Test]
    public function returns_first_non_null_result(): void
    {
        $first = $this->createStrategy(null);
        $second = $this->createStrategy(TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID'));
        $third = $this->createStrategy(TenantContext::fromResolution('globex', 'query', 'globex'));

        $chain = new TenantResolverChain([$first, $second, $third]);
        $context = $chain->resolve($this->makeRequest());

        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('header', $context->strategy);
    }

    #[Test]
    public function returns_default_when_all_strategies_return_null(): void
    {
        $first = $this->createStrategy(null);
        $second = $this->createStrategy(null);

        $chain = new TenantResolverChain([$first, $second]);
        $context = $chain->resolve($this->makeRequest());

        $this->assertTrue($context->isDefault());
        $this->assertSame('none', $context->strategy);
    }

    #[Test]
    public function returns_result_from_single_strategy(): void
    {
        $strategy = $this->createStrategy(TenantContext::fromResolution('acme', 'subdomain', 'acme.example.com'));

        $chain = new TenantResolverChain([$strategy]);
        $context = $chain->resolve($this->makeRequest());

        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('subdomain', $context->strategy);
    }

    #[Test]
    public function stops_after_first_match(): void
    {
        $callCount = 0;

        $first = new class($callCount) implements TenantResolverStrategyInterface {
            public function __construct(private int &$callCount) {}
            public function resolve(Request $request): ?TenantContext
            {
                $this->callCount++;
                return TenantContext::fromResolution('acme', 'header', 'value');
            }
        };

        $second = new class($callCount) implements TenantResolverStrategyInterface {
            public function __construct(private int &$callCount) {}
            public function resolve(Request $request): ?TenantContext
            {
                $this->callCount++;
                return TenantContext::fromResolution('globex', 'query', 'value');
            }
        };

        $chain = new TenantResolverChain([$first, $second]);
        $chain->resolve($this->makeRequest());

        $this->assertSame(1, $callCount);
    }

    private function createStrategy(?TenantContext $result): TenantResolverStrategyInterface
    {
        return new class($result) implements TenantResolverStrategyInterface {
            public function __construct(private readonly ?TenantContext $result) {}
            public function resolve(Request $request): ?TenantContext
            {
                return $this->result;
            }
        };
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
