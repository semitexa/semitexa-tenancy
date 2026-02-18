<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution;

use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Identification\Tenant;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;

final class TenantValidator
{
    public function __construct(
        private readonly TenantRepositoryInterface $repository,
    ) {}

    /**
     * Validate that the resolved tenant exists and is active.
     * Returns the Tenant entity if valid, null otherwise.
     * Default contexts are not validated (they bypass the repository).
     */
    public function validate(TenantContext $context): ?Tenant
    {
        if ($context->isDefault()) {
            return null;
        }

        return $this->repository->findActive($context->tenantId);
    }
}
