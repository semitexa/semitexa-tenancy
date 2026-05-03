<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Propagation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Application\Service\TenantAwareJobSerializer;

final class TenantAwareJobSerializerTest extends TestCase
{
    protected function tearDown(): void
    {
        CoroutineContextStore::clearFallback();
    }

    #[Test]
    public function wrap_adds_tenant_to_payload(): void
    {
        CoroutineContextStore::setFallback(TenantContext::fromResolution('acme', 'header', 'X-Tenant-ID'));

        $payload = ['action' => 'sync', 'data' => [1, 2, 3]];
        $wrapped = TenantAwareJobSerializer::wrap($payload);

        $this->assertArrayHasKey('_tenant', $wrapped);
        $this->assertSame('acme', $wrapped['_tenant']['tenantId']);
        $this->assertSame('header', $wrapped['_tenant']['strategy']);
        // Original keys preserved
        $this->assertSame('sync', $wrapped['action']);
    }

    #[Test]
    public function wrap_does_not_add_tenant_for_default_context(): void
    {
        CoroutineContextStore::setFallback(TenantContext::default());

        $payload = ['action' => 'sync'];
        $wrapped = TenantAwareJobSerializer::wrap($payload);

        $this->assertArrayNotHasKey('_tenant', $wrapped);
        $this->assertSame($payload, $wrapped);
    }

    #[Test]
    public function wrap_does_not_add_tenant_when_no_context(): void
    {
        $payload = ['action' => 'sync'];
        $wrapped = TenantAwareJobSerializer::wrap($payload);

        $this->assertArrayNotHasKey('_tenant', $wrapped);
        $this->assertSame($payload, $wrapped);
    }

    #[Test]
    public function unwrap_extracts_tenant_context(): void
    {
        $payload = [
            'action' => 'sync',
            '_tenant' => [
                'tenantId' => 'acme',
                'strategy' => 'header',
            ],
        ];

        [$cleaned, $context] = TenantAwareJobSerializer::unwrap($payload);

        $this->assertArrayNotHasKey('_tenant', $cleaned);
        $this->assertSame('sync', $cleaned['action']);
        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
        $this->assertSame('header', $context->strategy);
        $this->assertSame('queue', $context->source);
    }

    #[Test]
    public function unwrap_returns_null_context_when_no_tenant_key(): void
    {
        $payload = ['action' => 'sync'];

        [$cleaned, $context] = TenantAwareJobSerializer::unwrap($payload);

        $this->assertSame($payload, $cleaned);
        $this->assertNull($context);
    }

    #[Test]
    public function unwrap_and_restore_sets_fallback(): void
    {
        $payload = [
            'action' => 'sync',
            '_tenant' => [
                'tenantId' => 'globex',
                'strategy' => 'subdomain',
            ],
        ];

        $cleaned = TenantAwareJobSerializer::unwrapAndRestore($payload);

        $this->assertArrayNotHasKey('_tenant', $cleaned);
        $this->assertSame('sync', $cleaned['action']);

        $context = CoroutineContextStore::get();
        $this->assertNotNull($context);
        $this->assertSame('globex', $context->tenantId);
    }

    #[Test]
    public function unwrap_and_restore_does_nothing_without_tenant(): void
    {
        $payload = ['action' => 'sync'];

        $cleaned = TenantAwareJobSerializer::unwrapAndRestore($payload);

        $this->assertSame($payload, $cleaned);
        $this->assertNull(CoroutineContextStore::get());
    }

    #[Test]
    public function roundtrip_wrap_unwrap(): void
    {
        CoroutineContextStore::setFallback(TenantContext::fromResolution('acme', 'path', '/acme'));

        $original = ['key' => 'value', 'nested' => ['a' => 1]];
        $wrapped = TenantAwareJobSerializer::wrap($original);

        CoroutineContextStore::clearFallback();

        [$cleaned, $context] = TenantAwareJobSerializer::unwrap($wrapped);

        $this->assertSame($original, $cleaned);
        $this->assertNotNull($context);
        $this->assertSame('acme', $context->tenantId);
    }
}
