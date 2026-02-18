<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Tenancy\Exception\TenantContextImmutableException;
use Semitexa\Tenancy\Exception\TenantRequiredException;
use Swoole\Coroutine;

final class CoroutineContextStore
{
    private const CONTEXT_KEY = '__tenant_context';
    private const LOCK_KEY = '__tenant_context_locked';

    private static ?TenantContext $fallback = null;

    /**
     * Store tenant context for the current coroutine.
     * In HTTP (coroutine) mode, the context becomes immutable after the first set() call.
     */
    public static function set(TenantContext $context): void
    {
        if (self::inCoroutine()) {
            $coContext = Coroutine::getContext();

            if (isset($coContext[self::LOCK_KEY]) && $coContext[self::LOCK_KEY] === true) {
                throw new TenantContextImmutableException(
                    'Tenant context is immutable within an HTTP request. '
                    . 'Use CLI mode for tenant switching.',
                );
            }

            $coContext[self::CONTEXT_KEY] = $context;
            $coContext[self::LOCK_KEY] = true;

            return;
        }

        self::$fallback = $context;
    }

    /**
     * Get the current tenant context, or null if not set.
     */
    public static function get(): ?TenantContext
    {
        if (self::inCoroutine()) {
            $coContext = Coroutine::getContext();
            return $coContext[self::CONTEXT_KEY] ?? null;
        }

        return self::$fallback;
    }

    /**
     * Get the current tenant context, or throw if not set.
     */
    public static function getOrFail(): TenantContext
    {
        $context = self::get();

        if ($context === null) {
            throw new TenantRequiredException('No tenant context has been set');
        }

        return $context;
    }

    /**
     * Set fallback context for CLI/testing (non-coroutine mode).
     */
    public static function setFallback(TenantContext $context): void
    {
        self::$fallback = $context;
    }

    /**
     * Clear fallback context.
     */
    public static function clearFallback(): void
    {
        self::$fallback = null;
    }

    private static function inCoroutine(): bool
    {
        return class_exists(Coroutine::class, false) && Coroutine::getCid() > 0;
    }
}
