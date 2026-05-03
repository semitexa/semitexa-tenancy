<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\Resolver;

use Semitexa\Tenancy\Domain\Contract\TenantResolverInterface;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Domain\Model\LayerDefinition;

/**
 * Resolves tenant context by running each layer's strategy and building
 * a multilayer TenantContext. Used when TenancyLayersProvider(s) are present.
 */
final class MultilayerTenantResolver implements TenantResolverInterface
{
    /** @param LayerDefinition[] $layerDefinitions */
    public function __construct(
        private readonly array $layerDefinitions,
    ) {}

    public function resolve(Request $request): TenantContext
    {
        $layers = [];

        foreach ($this->layerDefinitions as $definition) {
            $strategy = $definition->strategy;
            if (!method_exists($strategy, 'resolve')) {
                continue;
            }

            $value = $strategy->resolve($request);
            if ($value !== null) {
                $layers[] = $value;
            }
        }

        if ($layers === []) {
            return TenantContext::default();
        }

        return new TenantContext(...$layers);
    }
}
