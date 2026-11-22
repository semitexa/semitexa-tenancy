<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Context;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Support\CoroutineLocal;
use Semitexa\Core\Tenant\DefaultTenantContextStore;
use Semitexa\Core\Tenant\TenantContextAccess;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Tenancy\Exception\TenantContextImmutableException;

/**
 * Code built outside the container reaches for
 * `new DefaultTenantContextStore()`. It must see the tenant this package's
 * store holds, inside a coroutine and outside one (tenant:run, a queue
 * worker, a scheduler run), or one tenant's rows are filed under 'default'.
 */
final class BareCoreStoreSeesTheActiveTenantTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContextStore::clearCurrent();
    }

    #[Test]
    public function the_slot_is_cores_constant_not_a_copy_of_its_text(): void
    {
        $key = (new \ReflectionClassConstant(TenantContextStore::class, 'CONTEXT_KEY'));

        self::assertSame(DefaultTenantContextStore::CONTEXT_KEY, $key->getValue());

        $source = (string) file_get_contents((string) (new \ReflectionClass(TenantContextStore::class))->getFileName());
        self::assertStringContainsString(
            'CONTEXT_KEY = DefaultTenantContextStore::CONTEXT_KEY;',
            $source,
            'A second copy of the string can drift from core\'s without any test noticing.',
        );
    }

    #[Test]
    public function outside_a_coroutine_two_tenants_in_turn_are_each_seen_by_a_bare_store(): void
    {
        $store = new TenantContextStore();
        $seen = [];

        foreach (['tenant-acme', 'tenant-globex'] as $tenantId) {
            $store->set(TenantContext::fromResolution($tenantId, 'test'));
            $seen[] = self::bareTenantId();
        }
        $store->clear();
        $seen[] = self::bareTenantId();

        self::assertSame(['tenant-acme', 'tenant-globex', null], $seen);
    }

    #[Test]
    public function set_fallback_and_swap_fallback_reach_a_bare_store_too(): void
    {
        $store = new TenantContextStore();

        $store->setFallback(TenantContext::fromResolution('tenant-acme', 'job'));
        self::assertSame('tenant-acme', self::bareTenantId());

        $previous = $store->swapFallback(TenantContext::fromResolution('tenant-globex', 'cli'));
        self::assertSame('tenant-globex', self::bareTenantId());

        $store->swapFallback($previous);
        self::assertSame('tenant-acme', self::bareTenantId());

        $store->swapFallback(null);
        self::assertNull(self::bareTenantId());
    }

    #[Test]
    public function a_request_start_that_empties_the_slot_does_not_hide_the_tenant_for_long(): void
    {
        $store = new TenantContextStore();
        $store->set(TenantContext::fromResolution('tenant-acme', 'cli'));

        // What Application::handleRequest() and the queue worker do first.
        CoroutineLocal::beginRequest();

        self::assertSame('tenant-acme', TenantContextAccess::tenantIdOrDefault($store->tryGet()));
        self::assertSame('tenant-acme', self::bareTenantId());
    }

    #[Test]
    public function inside_a_coroutine_each_tenant_is_seen_and_the_lock_still_holds(): void
    {
        $seen = [];
        $refused = false;
        $hooks = \Swoole\Runtime::getHookFlags();
        try {
            \Swoole\Coroutine\run(static function () use (&$seen, &$refused): void {
                foreach (['tenant-acme', 'tenant-globex'] as $tenantId) {
                    \Swoole\Coroutine::create(static function () use ($tenantId, &$seen, &$refused): void {
                        $store = new TenantContextStore();
                        $store->set(TenantContext::fromResolution($tenantId, 'test'));
                        // Yield, so the other tenant's coroutine runs in between.
                        \Swoole\Coroutine::sleep(0.005);
                        $seen[$tenantId] = self::bareTenantId();

                        if ($tenantId === 'tenant-acme') {
                            try {
                                $store->set(TenantContext::fromResolution('tenant-globex', 'test'));
                            } catch (TenantContextImmutableException) {
                                $refused = true;
                            }
                        }
                    });
                }
            });
        } finally {
            \Swoole\Coroutine::set(['hook_flags' => $hooks ?: SWOOLE_HOOK_ALL]);
        }

        ksort($seen);
        self::assertSame(['tenant-acme' => 'tenant-acme', 'tenant-globex' => 'tenant-globex'], $seen);
        self::assertTrue($refused, 'A second set() inside one request must still be refused.');
        self::assertNull(self::bareTenantId(), 'A coroutine\'s tenant must not reach the process outside it.');
    }

    private static function bareTenantId(): ?string
    {
        $context = (new DefaultTenantContextStore())->tryGet();

        return $context === null ? null : TenantContextAccess::tenantIdOrDefault($context);
    }
}
