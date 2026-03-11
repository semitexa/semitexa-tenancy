<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\Response;
use Semitexa\Tenancy\Context\TenantContext;

final class DefaultTenantErrorResponder implements TenantErrorResponderInterface
{
    public function tenantNotFound(TenantContext $context): Response
    {
        return Response::json([
            'error' => 'Tenant not found or inactive',
        ], 404);
    }

    public function tenantRequired(): Response
    {
        return Response::json([
            'error' => 'Tenant identification required',
        ], 400);
    }
}
