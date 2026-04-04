<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\HttpResponse;
use Semitexa\Tenancy\Context\TenantContext;

interface TenantErrorResponderInterface
{
    public function tenantNotFound(TenantContext $context): HttpResponse;

    public function tenantRequired(): HttpResponse;
}
