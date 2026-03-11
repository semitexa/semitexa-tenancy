<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Support\EnvReader;

final class EnvReaderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TEST_ENV_KEY');
        unset($_ENV['TEST_ENV_KEY'], $_SERVER['TEST_ENV_KEY']);
        putenv('TENANT_ACME_NAME');
        putenv('TENANT_ACME_STATUS');
        unset($_ENV['TENANT_ACME_NAME'], $_ENV['TENANT_ACME_STATUS']);
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
    public function get_reads_from_env_superglobal(): void
    {
        $_ENV['TEST_ENV_KEY'] = 'from_superglobal';

        $this->assertSame('from_superglobal', EnvReader::get('TEST_ENV_KEY'));
    }

    #[Test]
    public function get_reads_from_server_superglobal(): void
    {
        $_SERVER['TEST_ENV_KEY'] = 'from_server';

        $this->assertSame('from_server', EnvReader::get('TEST_ENV_KEY'));
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
}
