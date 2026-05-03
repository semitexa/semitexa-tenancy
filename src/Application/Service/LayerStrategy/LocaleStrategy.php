<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\LayerStrategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\LocaleValue;
use Semitexa\Core\Tenant\TenantLayerStrategyInterface;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;

class LocaleStrategy implements TenantLayerStrategyInterface
{
    public function __construct(
        private readonly TenantResolverStrategyInterface $resolver,
    ) {}

    public function layer(): TenantLayerInterface
    {
        return new LocaleLayer();
    }

    public function resolve(Request $request): ?TenantLayerValueInterface
    {
        $context = $this->resolver->resolve($request);

        if ($context === null || $context->isDefault()) {
            return null;
        }

        return LocaleValue::fromCode($context->tenantId);
    }
}
