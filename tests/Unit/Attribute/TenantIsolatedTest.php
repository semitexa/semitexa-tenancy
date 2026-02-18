<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Tenancy\Attribute\TenantIsolated;

final class TenantIsolatedTest extends TestCase
{
    #[Test]
    public function can_be_instantiated(): void
    {
        $attr = new TenantIsolated();
        $this->assertInstanceOf(TenantIsolated::class, $attr);
    }

    #[Test]
    public function is_a_class_attribute(): void
    {
        $ref = new \ReflectionClass(TenantIsolated::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);
        $attrInstance = $attrs[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }

    #[Test]
    public function can_be_read_from_annotated_class(): void
    {
        $testClass = new #[TenantIsolated] class {};
        $ref = new \ReflectionClass($testClass);
        $attrs = $ref->getAttributes(TenantIsolated::class);

        $this->assertCount(1, $attrs);
        $this->assertInstanceOf(TenantIsolated::class, $attrs[0]->newInstance());
    }
}
