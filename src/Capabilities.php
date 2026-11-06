<?php

declare(strict_types=1);

namespace Semitexa\Tenancy;

use Semitexa\Core\Attribute\Capability;

/**
 * What this package offers, for the capability catalog.
 *
 * Without this the package is invisible to anyone whose project has not
 * installed it - which is precisely the audience worth telling, since they are
 * the ones about to build it by hand. The convention is one `Capabilities` class
 * per package: a definite place to look, and a definite place for a guard to
 * check.
 *
 * Nothing reads this at runtime.
 */
#[Capability(
    id: 'tenancy.multi-tenant',
    summary: 'Tenant resolution and a scoped context that ORM, cache and live transport read from.',
    useWhen: 'One deployment serves several customers whose data must never meet.',
    avoidWhen: 'Single-tenant, and the isolation would be ceremony around a constant.',
    replaces: [
        'a tenant_id argument threaded through every method and forgotten in one',
        'a WHERE clause remembered per query, with no guard when it is missed',
    ],
    usage: <<<'USAGE'
        // .env. The tenant comes from the host (acme.shop.test): the shipped TenancyLayersProvider
        // decides, so TENANCY_STRATEGY is not read and an X-Tenant-ID header changes nothing.
        TENANCY_ENABLED=true
        TENANCY_BASE_DOMAIN=shop.test
        TENANTS=acme:Acme:active,globex:Globex:active
        TENANCY_REQUIRED=true

        // A subdomain not in TENANTS gets a 404; with TENANCY_REQUIRED a host with no tenant gets a 400.
        // On the model, its tenant column named (orm.persistence has the rest of the class):
        #[TenantScoped(strategy: 'same_storage', column: 'tenantId')]

        // In the OrderStore of orm.persistence. The ORM does not read the context by itself, so pass it,
        // asked for per call: a service outlives the request, and a tenant in a property reaches the next one.
        #[InjectAsReadonly]
        protected TenantContextStoreInterface $tenants;

        public function all(): array
        {
            $tenantId = TenantContextAccess::tenantId($this->tenants->tryGet());   // null: the read throws
            return $this->domainRepository(OrderResource::class)->forTenant($tenantId)->query()->fetchAll();
        }

        // Silent: on a model without #[TenantScoped], forTenant() filters nothing and every tenant reads every row.
        // #[TenantIsolated] refuses nothing: only the project graph reads it.
        // A timer has no tenant: inject TenantFanoutInterface; eachTenant(fn (string $tenantId) => ...) binds each in turn.
        // With an empty TENANTS it runs once as 'default' and binds nothing: tryGet() is then null.
        USAGE,
)]
final class Capabilities
{
}
