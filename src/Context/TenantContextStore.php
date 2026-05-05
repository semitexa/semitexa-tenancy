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

    public function __construct()
    {
        self::$shared ??= $this;
    }

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
        if ($this->inCoroutine()) {
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
        self::$registered = true;
        PerRequestStateRegistry::register(
            self::REGISTRY_NAME,
            static function (): void {
                $shared = self::$shared;
                if ($shared !== null) {
                    $shared->clear();
                }
                self::$fallback = null;
            },
        );
    }
}
