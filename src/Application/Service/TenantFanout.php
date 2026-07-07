<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Log\FallbackErrorLogger;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Core\Tenant\TenantFanoutInterface;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;

/**
 * Real multi-tenant fan-out: runs the work once per ACTIVE tenant, binding that
 * tenant's context around each call so #[TenantScoped] reads/writes inside the
 * callback resolve to the right tenant. Higher-priority override of the core
 * {@see SingleTenantFanout} (tenancy depends on core → wins the contract).
 *
 * BACKGROUND-only: it clear()s and set()s the coroutine-local context per
 * iteration, deliberately cycling the request-immutability lock the context
 * store enforces inside an HTTP request. A prior context (if any) is restored
 * at the end; one tenant's failure is isolated so the sweep still covers the rest.
 */
#[SatisfiesServiceContract(of: TenantFanoutInterface::class)]
final class TenantFanout implements TenantFanoutInterface
{
    #[InjectAsReadonly]
    protected TenantRepositoryInterface $tenants;

    #[InjectAsReadonly]
    protected TenantContextStoreInterface $contextStore;

    public function eachTenant(\Closure $work): void
    {
        $active = array_values(array_filter(
            $this->tenants->findAll(),
            static fn ($tenant): bool => $tenant->status === TenantStatus::Active,
        ));

        // Availability guard: tenancy can be installed with an EMPTY registry
        // (e.g. TENANTS unset on a single-user OS build that pulls tenancy
        // transitively). An empty fan-out would silently stop background
        // weaving/ticking, so degrade to a single 'default' pass — matching
        // SingleTenantFanout and TenantContextAccess::tenantIdOrDefault.
        if ($active === []) {
            $work('default');

            return;
        }

        $previous = $this->contextStore->tryGet();

        try {
            foreach ($active as $tenant) {
                // clear() drops the request-immutability lock so set() is allowed
                // on each iteration (set() locks; a background sweep must re-bind).
                $this->contextStore->clear();
                $this->contextStore->set(TenantContext::fromResolution($tenant->id, 'fanout'));

                try {
                    $work($tenant->id);
                } catch (\Throwable $e) {
                    // Isolate the failing tenant — the sweep must still reach the others.
                    FallbackErrorLogger::log('Tenant fan-out iteration failed', [
                        'tenant' => $tenant->id,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                } finally {
                    $this->contextStore->clear();
                }
            }
        } finally {
            if ($previous !== null) {
                $this->contextStore->set($previous);
            }
        }
    }
}
