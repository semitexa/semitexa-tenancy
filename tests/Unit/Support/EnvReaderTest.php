<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Application\Service\EnvReader;

final class EnvReaderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TEST_ENV_KEY');
        putenv('TENANT_ACME_NAME');
        putenv('TENANT_ACME_STATUS');
        putenv('TENANT_ACME_DOMAIN');
        putenv('TENANT_ACME_DOMAINS');
        putenv('TENANT_ACME_PUBLIC_DOMAIN');
        putenv('TENANT_ACME_PUBLIC_DOMAINS');
    }

    #[Test]
    public function get_returns_default_when_not_set(): void
    {
        $this->assertSame('fallback', EnvReader::get('TEST_ENV_KEY', 'fallback'));
    }

    #[Test]
    public function get_reads_from_getenv(): void
    {
        putenv('TEST_ENV_KEY=from_env');

        $this->assertSame('from_env', EnvReader::get('TEST_ENV_KEY'));
    }

    #[Test]
    public function get_bool_returns_true_for_true_string(): void
    {
        putenv('TEST_ENV_KEY=true');

        $this->assertTrue(EnvReader::getBool('TEST_ENV_KEY'));
    }

    #[Test]
    public function get_bool_returns_false_for_false_string(): void
    {
        putenv('TEST_ENV_KEY=false');

        $this->assertFalse(EnvReader::getBool('TEST_ENV_KEY'));
    }

    #[Test]
    public function get_bool_returns_default_when_not_set(): void
    {
        $this->assertTrue(EnvReader::getBool('TEST_ENV_KEY', true));
    }

    #[Test]
    public function get_bool_returns_true_for_one(): void
    {
        putenv('TEST_ENV_KEY=1');

        $this->assertTrue(EnvReader::getBool('TEST_ENV_KEY'));
    }

    #[Test]
    public function scan_detailed_tenants_finds_patterns(): void
    {
        putenv('TENANT_ACME_NAME=Acme Corp');
        putenv('TENANT_ACME_STATUS=active');

        $result = EnvReader::scanDetailedTenants();

        $this->assertArrayHasKey('acme', $result);
        $this->assertSame('Acme Corp', $result['acme']['name']);
        $this->assertSame('active', $result['acme']['status']);
    }

    #[Test]
    public function scan_detailed_tenants_reads_domain_metadata(): void
    {
        putenv('TENANT_ACME_NAME=Acme Corp');
        putenv('TENANT_ACME_DOMAIN=semitexa.test');
        putenv('TENANT_ACME_DOMAINS=www.semitexa.test, shop.semitexa.test');
        putenv('TENANT_ACME_PUBLIC_DOMAIN=semitexa.com');
        putenv('TENANT_ACME_PUBLIC_DOMAINS=www.semitexa.com, shop.semitexa.com');

        $result = EnvReader::scanDetailedTenants();

        $this->assertSame('semitexa.test', $result['acme']['config']['domain']);
        $this->assertSame(
            ['www.semitexa.test', 'shop.semitexa.test'],
            $result['acme']['config']['domains'],
        );
        $this->assertSame('semitexa.com', $result['acme']['config']['public_domain']);
        $this->assertSame(
            ['www.semitexa.com', 'shop.semitexa.com'],
            $result['acme']['config']['public_domains'],
        );
    }
}
