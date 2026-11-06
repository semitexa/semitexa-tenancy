<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Attribute;

use Attribute;

/**
 * Marks a payload or a handler as tenant-isolated: it runs only with a tenant
 * resolved. Without one the request is refused with a 403 before the handler
 * runs, by {@see \Semitexa\Tenancy\Pipeline\TenantIsolatedListener}.
 *
 * Usage:
 *   #[TenantIsolated]
 *   #[AsPayloadHandler(payload: ..., resource: ...)]
 *   class MyHandler { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class TenantIsolated
{
    public function __construct()
    {
    }
}
