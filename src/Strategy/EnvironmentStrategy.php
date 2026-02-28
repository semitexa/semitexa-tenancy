<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Strategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\Layer\EnvironmentLayer;
use Semitexa\Core\Tenant\Layer\EnvironmentValue;
use Semitexa\Core\Tenant\TenantLayerStrategyInterface;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;

class EnvironmentStrategy implements TenantLayerStrategyInterface
{
    public function __construct(
        private readonly TenantResolverStrategy $resolver,
    ) {}

    public function layer(): TenantLayerInterface
    {
        return new EnvironmentLayer();
    }

    public function resolve(Request $request): ?TenantLayerValueInterface
    {
        $context = $this->resolver->resolve($request);

        if ($context === null || $context->isDefault()) {
            return null;
        }

        return EnvironmentValue::fromValue($context->tenantId);
    }
}
