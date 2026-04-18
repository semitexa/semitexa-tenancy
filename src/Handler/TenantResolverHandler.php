<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Handler;

use Semitexa\Core\Request;
use Semitexa\Core\HttpResponse;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Core\Tenant\TenantResolverInterface;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Event\TenantNotFound;
use Semitexa\Tenancy\Event\TenantResolved;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;

/**
 * Resolves tenant from the HTTP request, validates against the repository,
 * stores in CoroutineContextStore, and dispatches events.
 *
 * This is NOT a payload handler — it runs before route dispatch in Application.php.
 * Integration into the request lifecycle happens in Phase 4.
 */
final class TenantResolverHandler
{
    private readonly TenantErrorResponderInterface $responder;

    public function __construct(
        private readonly TenantResolverInterface $resolver,
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantContextStoreInterface $tenantContextStore,
        private readonly ?EventDispatcherInterface $events = null,
        private readonly bool $requireTenant = false,
        ?TenantErrorResponderInterface $responder = null,
    ) {
        $this->responder = $responder ?? new DefaultTenantErrorResponder();
    }

    /**
     * Resolve and store tenant context for the current request.
     *
     * @return HttpResponse|null null = continue processing; HttpResponse = short-circuit with error
     */
    public function handle(Request $request): ?HttpResponse
    {
        $context = $this->resolver->resolve($request);

        if (!$context->isDefault()) {
            $tenant = $this->tenants->findActive($context->tenantId);

            if ($tenant === null) {
                $this->events?->dispatch(new TenantNotFound($context));

                return $this->responder->tenantNotFound($context);
            }

            $this->tenantContextStore->set($context);
            $this->events?->dispatch(new TenantResolved($context, $tenant));

            return null;
        }

        if ($this->requireTenant) {
            return $this->responder->tenantRequired();
        }

        $this->tenantContextStore->set($context);
        $this->events?->dispatch(new TenantResolved($context));

        return null;
    }
}
