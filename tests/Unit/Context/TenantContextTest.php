<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Context;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Exception\TenantRequiredException;

final class TenantContextTest extends TestCase
{
    #[Test]
    public function default_factory_creates_default_context(): void
    {
        $context = TenantContext::default();

        $this->assertSame('default', $context->tenantId);
        $this->assertSame('none', $context->strategy);
        $this->assertNull($context->source);
    }

    #[Test]
    public function is_default_returns_true_for_default_context(): void
    {
        $context = TenantContext::default();

        $this->assertTrue($context->isDefault());
    }

    #[Test]
    public function is_default_returns_false_for_real_tenant(): void
    {
        $context = new TenantContext('tenant-1', 'header', 'X-Tenant-ID');

        $this->assertFalse($context->isDefault());
    }

    #[Test]
    public function require_tenant_id_returns_id_for_real_tenant(): void
    {
        $context = new TenantContext('tenant-1', 'header', 'X-Tenant-ID');

        $this->assertSame('tenant-1', $context->requireTenantId());
    }

    #[Test]
    public function require_tenant_id_throws_for_default_context(): void
    {
        $context = TenantContext::default();

        $this->expectException(TenantRequiredException::class);

        $context->requireTenantId();
    }

    #[Test]
    public function for_serialization_returns_null_for_default(): void
    {
        $context = TenantContext::default();

        $this->assertNull($context->forSerialization());
    }

    #[Test]
    public function for_serialization_returns_array_for_real_tenant(): void
    {
        $context = new TenantContext('tenant-1', 'subdomain', 'tenant-1.example.com');

        $result = $context->forSerialization();

        $this->assertSame([
            'tenantId' => 'tenant-1',
            'strategy' => 'subdomain',
        ], $result);
    }

    #[Test]
    public function from_queue_payload_creates_context_with_queue_source(): void
    {
        $payload = [
            'tenantId' => 'tenant-1',
            'strategy' => 'header',
        ];

        $context = TenantContext::fromQueuePayload($payload);

        $this->assertSame('tenant-1', $context->tenantId);
        $this->assertSame('header', $context->strategy);
        $this->assertSame('queue', $context->source);
    }

    #[Test]
    public function from_queue_payload_defaults_strategy_to_queue(): void
    {
        $payload = [
            'tenantId' => 'tenant-1',
        ];

        $context = TenantContext::fromQueuePayload($payload);

        $this->assertSame('queue', $context->strategy);
        $this->assertSame('queue', $context->source);
    }

    #[Test]
    public function context_is_readonly(): void
    {
        $context = new TenantContext('tenant-1', 'header', 'X-Tenant-ID');

        $this->assertSame('tenant-1', $context->tenantId);
        $this->assertSame('header', $context->strategy);
        $this->assertSame('X-Tenant-ID', $context->source);
    }
}
