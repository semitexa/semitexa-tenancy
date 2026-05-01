<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\Resolver\Strategy;

use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

final class StrategyChain implements TenantResolverStrategyInterface
{
    /** @var list<TenantResolverStrategyInterface> */
    private readonly array $strategies;

    /**
     * @param list<TenantResolverStrategyInterface> $strategies
     */
    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
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
