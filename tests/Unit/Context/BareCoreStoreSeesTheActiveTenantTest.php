<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Context;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Lifecycle\CarriedContext;
use Semitexa\Core\Lifecycle\PerRequestStateRegistry;
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
 *
 * Outside a coroutine core's store asks this package's store; there is no
 * copy of the tenant that a reset could miss or empty.
 */
final class BareCoreStoreSeesTheActiveTenantTest extends TestCase
{
    /**
     * Every piece of process-wide state a test here can touch, as it was
     * before the test: the store's statics, what it registers with core on
     * first use, and the coroutine-local store's CLI half.
     *
     * @var array{fallback: mixed, registered: mixed, resetters: mixed, carried: mixed, cli: mixed}
     */
    private array $before;

    /** @var (\Closure(): ?\Semitexa\Core\Tenant\TenantContextInterface)|null */
    private ?\Closure $readerBefore = null;

    protected function setUp(): void
    {
        $this->before = [
            'fallback' => self::property(TenantContextStore::class, 'fallback')->getValue(),
            'registered' => self::property(TenantContextStore::class, 'registered')->getValue(),
            'resetters' => self::property(PerRequestStateRegistry::class, 'resetters')->getValue(),
            'carried' => self::property(CarriedContext::class, 'keys')->getValue(),
            'cli' => self::property(CoroutineLocal::class, 'cliStore')->getValue(),
        ];
        $this->readerBefore = DefaultTenantContextStore::readOutsideCoroutineFrom(null);

        // A store nobody has used yet in this process.
        self::property(TenantContextStore::class, 'fallback')->setValue(null, null);
        self::property(TenantContextStore::class, 'registered')->setValue(null, false);
        CoroutineLocal::remove(DefaultTenantContextStore::CONTEXT_KEY);
    }

    protected function tearDown(): void
    {
        self::property(TenantContextStore::class, 'fallback')->setValue(null, $this->before['fallback']);
        self::property(TenantContextStore::class, 'registered')->setValue(null, $this->before['registered']);
        self::property(PerRequestStateRegistry::class, 'resetters')->setValue(null, $this->before['resetters']);
        self::property(CarriedContext::class, 'keys')->setValue(null, $this->before['carried']);
        self::property(CoroutineLocal::class, 'cliStore')->setValue(null, $this->before['cli']);
        DefaultTenantContextStore::readOutsideCoroutineFrom($this->readerBefore);
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

    /**
     * Application::handleRequest() and the queue worker call beginRequest()
     * first. The bare store is read straight after it, before anything asks
     * this package's store: a copy republished on the next tenancy read
     * answered 'default' here.
     */
    #[Test]
    public function a_bare_store_read_straight_after_a_request_start_still_sees_the_tenant(): void
    {
        (new TenantContextStore())->set(TenantContext::fromResolution('tenant-acme', 'cli'));

        CoroutineLocal::beginRequest();

        self::assertSame('tenant-acme', self::bareTenantId());
    }

    /**
     * The per-request reset can run inside a coroutine while the tenant was
     * set outside one. A copy in the CLI half of the slot could not be
     * reached from there and kept answering with the tenant just reset.
     */
    #[Test]
    public function a_reset_run_inside_a_coroutine_leaves_no_tenant_for_a_bare_store_outside(): void
    {
        (new TenantContextStore())->set(TenantContext::fromResolution('tenant-acme', 'cli'));
        self::assertSame('tenant-acme', self::bareTenantId());

        $resetters = self::property(PerRequestStateRegistry::class, 'resetters')->getValue();
        self::assertIsArray($resetters);
        $reset = $resetters['tenant_context_store'] ?? null;
        self::assertIsCallable($reset);

        $hooks = \Swoole\Runtime::getHookFlags();
        try {
            \Swoole\Coroutine\run(static function () use ($reset): void {
                $reset();
            });
        } finally {
            \Swoole\Coroutine::set(['hook_flags' => $hooks ?: SWOOLE_HOOK_ALL]);
        }

        self::assertNull(self::bareTenantId());
        self::assertNull((new TenantContextStore())->tryGet());
    }

    #[Test]
    public function the_tenant_is_not_copied_into_the_slot_outside_a_coroutine(): void
    {
        (new TenantContextStore())->set(TenantContext::fromResolution('tenant-acme', 'cli'));

        self::assertFalse(
            CoroutineLocal::has(DefaultTenantContextStore::CONTEXT_KEY),
            'One owner outside a coroutine: a copy is what a reset misses.',
        );
    }

    #[Test]
    public function inside_a_coroutine_each_tenant_is_seen_and_the_lock_still_holds(): void
    {
        // A tenant active outside must not answer for a coroutine that has none.
        (new TenantContextStore())->set(TenantContext::fromResolution('tenant-cli', 'cli'));

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
                \Swoole\Coroutine::create(static function () use (&$seen): void {
                    \Swoole\Coroutine::sleep(0.005);
                    $seen['none'] = self::bareTenantId();
                });
            });
        } finally {
            \Swoole\Coroutine::set(['hook_flags' => $hooks ?: SWOOLE_HOOK_ALL]);
        }

        ksort($seen);
        self::assertSame(['none' => null, 'tenant-acme' => 'tenant-acme', 'tenant-globex' => 'tenant-globex'], $seen);
        self::assertTrue($refused, 'A second set() inside one request must still be refused.');
        self::assertSame('tenant-cli', self::bareTenantId(), 'A coroutine\'s tenant must not reach the process outside it.');
    }

    private static function bareTenantId(): ?string
    {
        $context = (new DefaultTenantContextStore())->tryGet();

        return $context === null ? null : TenantContextAccess::tenantIdOrDefault($context);
    }

    /** @param class-string $class */
    private static function property(string $class, string $name): \ReflectionProperty
    {
        return new \ReflectionProperty($class, $name);
    }
}
