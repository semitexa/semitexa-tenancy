<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Strategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\LocaleValue;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;

class LocaleStrategy
{
    public function __construct(
        private readonly TenantResolverStrategy $resolver,
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
