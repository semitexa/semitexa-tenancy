<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

final class CoroutineContextStore
{
    private function __construct()
    {
    }

    public static function set(TenantContext $context): void
    {
        TenantContextStore::shared()->set($context);
    }

    public static function getOrFail(): TenantContext
    {
        $context = self::get();

        return $context ?? throw new \Semitexa\Tenancy\Exception\TenantRequiredException('No tenant context has been set');
    }

    public static function setFallback(TenantContext $context): void
    {
        TenantContextStore::shared()->setFallback($context);
    }

    public static function swapFallback(?TenantContext $context): ?TenantContext
    {
        $previous = TenantContextStore::shared()->swapFallback($context);

        return $previous instanceof TenantContext ? $previous : null;
    }

    public static function clearFallback(): void
    {
        TenantContextStore::shared()->clear();
    }

    public static function get(): ?TenantContext
    {
        $context = TenantContextStore::shared()->tryGet();

        return $context instanceof TenantContext ? $context : null;
    }
}
