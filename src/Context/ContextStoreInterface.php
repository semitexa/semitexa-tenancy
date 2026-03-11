<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Tenancy\Exception\TenantRequiredException;

interface ContextStoreInterface
{
    public static function set(TenantContext $context): void;

    public static function get(): ?TenantContext;

    /**
     * @throws TenantRequiredException
     */
    public static function getOrFail(): TenantContext;
}
