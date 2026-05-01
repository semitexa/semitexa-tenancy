<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service\LayerStrategy;

use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\OrganizationValue;
use Semitexa\Core\Tenant\TenantLayerStrategyInterface;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;
use Semitexa\Tenancy\Context\TenantContext;

class OrganizationStrategy implements TenantLayerStrategyInterface
{
    public function __construct(
        private readonly TenantResolverStrategyInterface $resolver,
    ) {}

    public function layer(): TenantLayerInterface
    {
        return new OrganizationLayer();
    }

    public function resolve(Request $request): ?TenantLayerValueInterface
    {
        $context = $this->resolver->resolve($request);

        if ($context === null || $context->isDefault()) {
            return null;
        }

        return new OrganizationValue($context->tenantId);
    }
}
