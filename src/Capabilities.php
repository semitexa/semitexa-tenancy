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
)]
final class Capabilities
{
}
