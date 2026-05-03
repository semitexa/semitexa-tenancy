<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Resolution\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\StrategyChain;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

final class StrategyChainTest extends TestCase
{
    #[Test]
    public function returns_first_matching_context(): void
    {
        $chain = new StrategyChain([
            new class implements TenantResolverStrategyInterface {
                public function resolve(Request $request): ?TenantContext
                {
                    return null;
                }
            },
            new class implements TenantResolverStrategyInterface {
                public function resolve(Request $request): ?TenantContext
                {
                    return TenantContext::fromResolution('demo', 'domain', 'demo.semitexa.test');
                }
            },
        ]);

        $context = $chain->resolve($this->makeRequest());

        $this->assertNotNull($context);
        $this->assertSame('demo', $context->tenantId);
        $this->assertSame('domain', $context->strategy);
    }

    #[Test]
    public function returns_null_when_nothing_matches(): void
    {
        $chain = new StrategyChain([
            new class implements TenantResolverStrategyInterface {
                public function resolve(Request $request): ?TenantContext
                {
                    return null;
                }
            },
        ]);

        $this->assertNull($chain->resolve($this->makeRequest()));
    }

    private function makeRequest(): Request
    {
        return new Request(
            method: 'GET',
            uri: '/',
            headers: [],
            query: [],
            post: [],
            server: ['HTTP_HOST' => 'demo.semitexa.test'],
            cookies: [],
        );
    }
}
