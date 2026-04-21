<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\HttpResponse;
use Semitexa\Core\Tenant\TenantContextInterface;

interface TenantErrorResponderInterface
{
    public function tenantNotFound(TenantContextInterface $context): HttpResponse;

    public function tenantRequired(): HttpResponse;
}
