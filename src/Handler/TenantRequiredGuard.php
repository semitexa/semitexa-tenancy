<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\HttpResponse;
use Semitexa\Tenancy\Context\CoroutineContextStore;

/**
 * Guard that can be called from handlers/endpoints that require a tenant.
 * Returns a 403 response if no tenant is set, null otherwise.
 */
final class TenantRequiredGuard
{
    /**
     * @return HttpResponse|null null = tenant is present; HttpResponse = short-circuit with error
     */
    public function check(): ?HttpResponse
    {
        $context = CoroutineContextStore::get();

        if ($context === null || $context->isDefault()) {
            return HttpResponse::json([
                'error' => 'This endpoint requires a tenant context',
            ], 403);
        }

        return null;
    }
}
