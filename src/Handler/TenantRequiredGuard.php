<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\Response;
use Semitexa\Tenancy\Context\CoroutineContextStore;

/**
 * Guard that can be called from handlers/endpoints that require a tenant.
 * Returns a 403 response if no tenant is set, null otherwise.
 */
final class TenantRequiredGuard
{
    /**
     * @return Response|null null = tenant is present; Response = short-circuit with error
     */
    public function check(): ?Response
    {
        $context = CoroutineContextStore::get();

        if ($context === null || $context->isDefault()) {
            return Response::json([
                'error' => 'This endpoint requires a tenant context',
            ], 403);
        }

        return null;
    }
}
