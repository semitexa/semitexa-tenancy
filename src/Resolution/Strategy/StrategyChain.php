<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

final class StrategyChain implements TenantResolverStrategy
{
    /** @var list<TenantResolverStrategy> */
    private readonly array $strategies;

    /**
     * @param list<TenantResolverStrategy> $strategies
     */
    public function __construct(array $strategies)
    {
        $this->strategies = array_values($strategies);
    }

    public function resolve(Request $request): ?TenantContext
    {
        foreach ($this->strategies as $strategy) {
            $context = $strategy->resolve($request);

            if ($context !== null) {
                return $context;
            }
        }

        return null;
    }
}
