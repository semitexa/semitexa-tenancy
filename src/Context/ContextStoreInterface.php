<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Exception\TenantRequiredException;

interface ContextStoreInterface extends TenantContextStoreInterface
{
    /**
     * @throws TenantRequiredException
     */
    public function getOrFail(): TenantContextInterface;

    public function setFallback(TenantContextInterface $context): void;

    public function swapFallback(?TenantContextInterface $context): ?TenantContextInterface;
}
