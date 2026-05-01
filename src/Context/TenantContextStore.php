<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Tenancy\Domain\Model\Tenant;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
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

    private static ?self $shared = null;

    private static ?TenantContextInterface $fallback = null;

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
        self::$fallback = $context;
    }

    public function swapFallback(?TenantContextInterface $context): ?TenantContextInterface
    {
        $previous = self::$fallback;
        self::$fallback = $context;

        return $previous;
    }

    private function inCoroutine(): bool
    {
        return class_exists(\Swoole\Coroutine::class, false) && \Swoole\Coroutine::getCid() > 0;
    }
}
