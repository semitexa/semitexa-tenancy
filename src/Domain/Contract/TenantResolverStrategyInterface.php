<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Domain\Contract;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

interface TenantResolverStrategyInterface
{
    public function resolve(Request $request): ?TenantContext;
}
