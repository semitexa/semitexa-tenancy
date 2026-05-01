<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\LayerStrategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\TenantLayerStrategyInterface;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

/**
 * Generic adapter that bridges a TenantResolverStrategyInterface to a TenantLayerStrategyInterface.
 *
 * Replaces the need for individual OrganizationStrategy/LocaleStrategy/etc. wrappers
 * when only the layer and value factory differ.
 */
class LayerStrategyAdapter implements TenantLayerStrategyInterface
{
    /**
     * @param TenantLayerInterface $layer The layer this strategy resolves
     * @param TenantResolverStrategyInterface $resolver The underlying resolution strategy
     * @param \Closure(string): ?TenantLayerValueInterface $valueFactory Creates a layer value from tenant ID
     */
    public function __construct(
        private readonly TenantLayerInterface $layer,
        private readonly TenantResolverStrategyInterface $resolver,
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
