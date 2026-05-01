<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Tenancy\Domain\Contract\TenantErrorResponderInterface;

use Semitexa\Tenancy\Domain\Model\Tenant;

use Semitexa\Core\HttpResponse;
use Semitexa\Tenancy\Context\TenantContext;

final class DefaultTenantErrorResponder implements TenantErrorResponderInterface
{
    public function tenantNotFound(TenantContext $context): HttpResponse
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
