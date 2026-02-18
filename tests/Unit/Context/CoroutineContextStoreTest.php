<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Context;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Exception\TenantRequiredException;

/**
 * Tests CLI/fallback mode only (no Swoole coroutine).
 * HTTP immutability guard requires Swoole coroutines — tested in integration phase.
 */
final class CoroutineContextStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        CoroutineContextStore::clearFallback();
    }

    #[Test]
    public function get_returns_null_when_no_context_set(): void
    {
        $this->assertNull(CoroutineContextStore::get());
    }

    #[Test]
    public function set_fallback_stores_context(): void
    {
        $context = new TenantContext('tenant-1', 'header', 'X-Tenant-ID');

        CoroutineContextStore::setFallback($context);

        $this->assertSame($context, CoroutineContextStore::get());
    }

    #[Test]
    public function clear_fallback_removes_context(): void
    {
        CoroutineContextStore::setFallback(new TenantContext('tenant-1', 'header'));

        CoroutineContextStore::clearFallback();

        $this->assertNull(CoroutineContextStore::get());
    }

    #[Test]
    public function get_or_fail_throws_when_no_context(): void
    {
        $this->expectException(TenantRequiredException::class);

        CoroutineContextStore::getOrFail();
    }

    #[Test]
    public function get_or_fail_returns_context_when_set(): void
    {
        $context = new TenantContext('tenant-1', 'header');

        CoroutineContextStore::setFallback($context);

        $this->assertSame($context, CoroutineContextStore::getOrFail());
    }

    #[Test]
    public function set_uses_fallback_outside_coroutine(): void
    {
        $context = new TenantContext('tenant-1', 'header');

        CoroutineContextStore::set($context);

        $this->assertSame($context, CoroutineContextStore::get());
    }

    #[Test]
    public function set_allows_multiple_calls_outside_coroutine(): void
    {
        $first = new TenantContext('tenant-1', 'header');
        $second = new TenantContext('tenant-2', 'subdomain');

        CoroutineContextStore::set($first);
        CoroutineContextStore::set($second);

        $this->assertSame($second, CoroutineContextStore::get());
    }
}
