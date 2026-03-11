<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Strategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\TenantLayerStrategyInterface;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;

/**
 * Generic adapter that bridges a TenantResolverStrategy to a TenantLayerStrategyInterface.
 *
 * Replaces the need for individual OrganizationStrategy/LocaleStrategy/etc. wrappers
 * when only the layer and value factory differ.
 */
class LayerStrategyAdapter implements TenantLayerStrategyInterface
{
    /**
     * @param TenantLayerInterface $layer The layer this strategy resolves
     * @param TenantResolverStrategy $resolver The underlying resolution strategy
     * @param \Closure(string): ?TenantLayerValueInterface $valueFactory Creates a layer value from tenant ID
     */
    public function __construct(
        private readonly TenantLayerInterface $layer,
        private readonly TenantResolverStrategy $resolver,
        private readonly \Closure $valueFactory,
    ) {}

    public function layer(): TenantLayerInterface
    {
        return $this->layer;
    }

    public function resolve(Request $request): ?TenantLayerValueInterface
    {
        $context = $this->resolver->resolve($request);

        if ($context === null || $context->isDefault()) {
            return null;
        }

        return ($this->valueFactory)($context->getTenantId());
    }
}
