<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\HttpResponse;
use Semitexa\Core\Tenant\TenantContextAccess;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Context\TenantContextStore;

/**
 * Guard that can be called from handlers/endpoints that require a tenant.
 * Returns a 403 response if no tenant is set, null otherwise.
 */
final class TenantRequiredGuard
{
    public function __construct(
        private readonly ?TenantContextStoreInterface $tenantContextStore = null,
    ) {}

    /**
     * @return HttpResponse|null null = tenant is present; HttpResponse = short-circuit with error
     */
    public function check(): ?HttpResponse
    {
        $context = ($this->tenantContextStore ?? TenantContextStore::shared())->tryGet();

        if (TenantContextAccess::isDefault($context)) {
            return HttpResponse::json([
                'error' => 'This endpoint requires a tenant context',
            ], 403);
        }

        return null;
    }
}
