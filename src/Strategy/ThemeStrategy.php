<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Strategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\Layer\ThemeLayer;
use Semitexa\Core\Tenant\Layer\ThemeValue;
use Semitexa\Core\Tenant\TenantLayerStrategyInterface;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;

class ThemeStrategy implements TenantLayerStrategyInterface
{
    public function __construct(
        private readonly TenantResolverStrategy $resolver,
    ) {}

    public function layer(): TenantLayerInterface
    {
        return new ThemeLayer();
    }

    public function resolve(Request $request): ?TenantLayerValueInterface
    {
        $context = $this->resolver->resolve($request);

        if ($context === null || $context->isDefault()) {
            return null;
        }

        return ThemeValue::fromName($context->tenantId);
    }
}
