<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\Response;
use Semitexa\Tenancy\Context\TenantContext;

interface TenantErrorResponderInterface
{
    public function tenantNotFound(TenantContext $context): Response;

    public function tenantRequired(): Response;
}
