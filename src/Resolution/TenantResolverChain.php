<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;

final class TenantResolverChain implements TenantResolverInterface
{
    /** @param TenantResolverStrategy[] $strategies */
    public function __construct(
        private readonly array $strategies,
    ) {}

    public function resolve(Request $request): TenantContext
    {
        foreach ($this->strategies as $strategy) {
            $context = $strategy->resolve($request);

            if ($context !== null) {
                return $context;
            }
        }

        return TenantContext::default();
    }
}
