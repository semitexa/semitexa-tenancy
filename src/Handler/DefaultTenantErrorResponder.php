<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\HttpResponse;
use Semitexa\Core\Tenant\TenantContextInterface;

final class DefaultTenantErrorResponder implements TenantErrorResponderInterface
{
    public function tenantNotFound(TenantContextInterface $context): HttpResponse
    {
        return HttpResponse::json([
            'error' => 'Tenant not found or inactive',
        ], 404);
    }

    public function tenantRequired(): HttpResponse
    {
        return HttpResponse::json([
            'error' => 'Tenant identification required',
        ], 400);
    }
}
