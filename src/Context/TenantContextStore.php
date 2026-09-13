<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Lifecycle\PerRequestStateRegistry;
use Semitexa\Core\Support\CoroutineLocal;
use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Exception\TenantContextImmutableException;
use Semitexa\Tenancy\Exception\TenantRequiredException;

#[SatisfiesServiceContract(of: TenantContextStoreInterface::class)]
final class TenantContextStore implements ContextStoreInterface
{
    private const CONTEXT_KEY = 'semitexa.tenancy.tenant_context';
    private const LOCK_KEY = 'semitexa.tenancy.tenant_context_locked';
    private const REGISTRY_NAME = 'tenant_context_store';

    private static ?self $shared = null;

    private static ?TenantContextInterface $fallback = null;

    private static bool $registered = false;

    /**
     * The process-wide instance, created on first ask.
     *
     * A constructor used to seed this with `self::$shared ??= $this`, and the
     * container never called it — container-managed classes are built with
     * newInstanceWithoutConstructor(). Nothing broke, because every piece of
     * state this class holds lives in CoroutineLocal under class constants, so
     * one instance is interchangeable with another. The line was doing nothing
     * in either direction; it is gone rather than moved to initialize(), where
     * it would still do nothing.
     */
    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

    public function get(): TenantContextInterface
    {
        return $this->tryGet() ?? TenantContext::default();
    }

    public function tryGet(): ?TenantContextInterface
    {
        if ($this->inCoroutine()) {
            $context = CoroutineLocal::get(self::CONTEXT_KEY);

            return $context instanceof TenantContextInterface ? $context : null;
        }

        return self::$fallback;
    }

    public function getOrFail(): TenantContextInterface
    {
        return $this->tryGet() ?? throw new TenantRequiredException('No tenant context has been set');
    }

    public function set(TenantContextInterface $context): void
    {
        self::ensureRegistered();
        if ($this->inCoroutine()) {
            if (CoroutineLocal::get(self::LOCK_KEY, false) === true) {
                throw new TenantContextImmutableException(
                    'Tenant context is immutable within an HTTP request. Use CLI mode for tenant switching.',
                );
            }

            CoroutineLocal::set(self::CONTEXT_KEY, $context);
            CoroutineLocal::set(self::LOCK_KEY, true);

            return;
        }

        self::$fallback = $context;
    }

    public function clear(): void
    {
        self::clearCurrent();
    }

    /**
     * Wipe the tenant context of the current execution, instance or not.
     *
     * Every piece of state this class holds lives in CoroutineLocal under
     * class constants, or in a static fallback, so clearing needs no
     * particular instance — and must not wait for one. The per-request
     * callback used to go through self::$shared and skipped the wipe entirely
     * whenever nobody had called shared(): a container-injected store that
     * resolved a tenant would leave that tenant's context behind for whatever
     * ran next on the same coroutine. Raised in review of
     * semitexa-tenancy#33.
     */
    public static function clearCurrent(): void
    {
        if (class_exists(\Swoole\Coroutine::class, false) && \Swoole\Coroutine::getCid() > 0) {
            CoroutineLocal::remove(self::CONTEXT_KEY);
            CoroutineLocal::remove(self::LOCK_KEY);

            return;
        }

        self::$fallback = null;
    }

    public function setFallback(TenantContextInterface $context): void
    {
        self::ensureRegistered();
        self::$fallback = $context;
    }

    public function swapFallback(?TenantContextInterface $context): ?TenantContextInterface
    {
        self::ensureRegistered();
        $previous = self::$fallback;
        self::$fallback = $context;

        return $previous;
    }

    private function inCoroutine(): bool
    {
        return class_exists(\Swoole\Coroutine::class, false) && \Swoole\Coroutine::getCid() > 0;
    }

    /**
     * Lazy registration with the framework's per-request lifecycle.
     *
     * Both set() and setFallback() are per-execution APIs in practice — every
     * production caller (TenancyPhase, TenantAwareJobSerializer, TenantRunCommand)
     * establishes the tenant before the unit of work begins and lets the
     * framework wipe it after. Carrying tenant identity past the request that
     * established it is a multi-tenant safety bug — tenant A's request must
     * not leave its TenantContext in the store for a later anonymous request
     * (which could be tenant B) to observe.
     *
     * Triggered on first set/setFallback/swap so workers that never touch a
     * tenant pay zero overhead. Same lazy pattern as CurrentRequestStore and
     * AuthContextStore.
     */
    private static function ensureRegistered(): void
    {
        if (self::$registered) {
            return;
        }

        // semitexa/core is the sole require of this package, so the registry
        // is always there; the guard only decided whether per-request cleanup
        // got registered at all, and skipping it silently is how a tenant
        // leaks into the next unit of work.
        PerRequestStateRegistry::register(
            self::REGISTRY_NAME,
            static function (): void {
                // Not through self::$shared: an injected store can set a
                // tenant while nothing ever called shared(), and this
                // callback is the only thing standing between that tenant
                // and the next unit of work on this coroutine.
                self::clearCurrent();
                self::$fallback = null;
            },
        );

        self::$registered = true;
    }
}
