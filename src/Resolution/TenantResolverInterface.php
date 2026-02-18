<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Resolution;

use Semitexa\Core\Request;
use Semitexa\Tenancy\Context\TenantContext;

interface TenantResolverInterface
{
    public function resolve(Request $request): TenantContext;
}
