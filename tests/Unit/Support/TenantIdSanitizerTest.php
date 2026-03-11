<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Support\TenantIdSanitizer;

final class TenantIdSanitizerTest extends TestCase
{
    #[Test]
    public function removes_special_characters(): void
    {
        $this->assertSame('acmecorp', TenantIdSanitizer::sanitize('ac!me@corp'));
    }

    #[Test]
    public function allows_alphanumeric_hyphens_underscores(): void
    {
        $this->assertSame('my-tenant_01', TenantIdSanitizer::sanitize('my-tenant_01'));
    }

    #[Test]
    public function returns_null_for_empty_after_sanitize(): void
    {
        $this->assertNull(TenantIdSanitizer::sanitize('!!!'));
    }

    #[Test]
    public function returns_null_for_empty_input(): void
    {
        $this->assertNull(TenantIdSanitizer::sanitize(''));
    }

    #[Test]
    public function truncates_to_max_length(): void
    {
        $long = str_repeat('a', 100);
        $this->assertSame(64, strlen(TenantIdSanitizer::sanitize($long)));
    }

    #[Test]
    public function respects_custom_max_length(): void
    {
        $long = str_repeat('a', 100);
        $this->assertSame(10, strlen(TenantIdSanitizer::sanitize($long, 10)));
    }

    #[Test]
    public function strips_xss_attempts(): void
    {
        $this->assertSame('scriptalertscript', TenantIdSanitizer::sanitize('<script>alert()</script>'));
    }
}
