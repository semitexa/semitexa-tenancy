<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Application\Service\TenantUrlResolver;

final class TenantUrlResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_ENV');
        putenv('TENANT_DEMO_DOMAIN');
        putenv('TENANT_DEMO_DOMAINS');
        putenv('TENANT_DEMO_PUBLIC_DOMAIN');
        putenv('TENANT_DEMO_PUBLIC_DOMAINS');
    }

    #[Test]
    public function resolve_url_prefers_local_domain_in_dev(): void
    {
        putenv('APP_ENV=dev');
        putenv('TENANT_DEMO_DOMAIN=framework.semitexa.test');
        putenv('TENANT_DEMO_PUBLIC_DOMAIN=framework.semitexa.com');

        $this->assertSame(
            'http://framework.semitexa.test/demo',
            TenantUrlResolver::resolveUrl('demo', '/demo'),
        );
    }

    #[Test]
    public function resolve_url_prefers_public_domain_in_production(): void
    {
        putenv('APP_ENV=prod');
        putenv('TENANT_DEMO_DOMAIN=framework.semitexa.test');
        putenv('TENANT_DEMO_PUBLIC_DOMAIN=framework.semitexa.com');

        $this->assertSame(
            'https://framework.semitexa.com/demo',
            TenantUrlResolver::resolveUrl('demo', '/demo'),
        );
    }

    #[Test]
    public function resolve_url_falls_back_to_local_domain_when_public_is_missing(): void
    {
        putenv('APP_ENV=prod');
        putenv('TENANT_DEMO_DOMAIN=framework.semitexa.test');
        putenv('TENANT_DEMO_DOMAINS=');
        putenv('TENANT_DEMO_PUBLIC_DOMAIN=');
        putenv('TENANT_DEMO_PUBLIC_DOMAINS=');

        $this->assertSame(
            'https://framework.semitexa.test/demo',
            TenantUrlResolver::resolveUrl('demo', '/demo'),
        );
    }

    #[Test]
    public function resolve_url_falls_back_to_public_domain_when_local_is_missing(): void
    {
        putenv('APP_ENV=dev');
        putenv('TENANT_DEMO_DOMAIN=');
        putenv('TENANT_DEMO_DOMAINS=');
        putenv('TENANT_DEMO_PUBLIC_DOMAIN=framework.semitexa.com');
        putenv('TENANT_DEMO_PUBLIC_DOMAINS=');

        $this->assertSame(
            'http://framework.semitexa.com/demo',
            TenantUrlResolver::resolveUrl('demo', '/demo'),
        );
    }
}
