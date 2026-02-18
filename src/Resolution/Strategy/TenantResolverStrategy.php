<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution\Strategy;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

interface TenantResolverStrategy
{
    public function resolve(Request $request): ?TenantContext;
}
