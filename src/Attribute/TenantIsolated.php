<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Attribute;

use Attribute;

/**
 * Marks a handler or service as tenant-isolated.
 *
 * When present on a handler class, the framework should enforce that
 * a valid (non-default) tenant context exists before executing the handler.
 * If no tenant is set, TenantRequiredGuard returns a 403.
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
