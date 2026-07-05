<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Tenancy\Domain\Model\LayerDefinition;
use Semitexa\Tenancy\Application\Service\Resolver\MultilayerTenantResolver;
use Semitexa\Tenancy\Application\Service\Resolver\TenantResolverChain;
use Semitexa\Tenancy\Application\Service\TenancyBootstrapper;

final class TenancyBootstrapperTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(TenancyBootstrapper::class);
        $property = $reflection->getProperty('discoveredLayerDefinitions');
        $property->setValue(null, null);
    }

    #[Test]
    public function auto_creates_class_discovery_when_none_is_injected(): void
    {
        $bootstrapper = new TenancyBootstrapper(new TenantContextStore());

        $this->assertInstanceOf(MultilayerTenantResolver::class, $bootstrapper->getResolver());
    }

    #[Test]
    public function uses_injected_class_discovery_for_multilayer_resolution(): void
    {
        $classDiscovery = $this->createMock(ClassDiscovery::class);
        $classDiscovery->expects($this->once())->method('initialize');
        $classDiscovery->expects($this->once())
            ->method('findClassesWithAttribute')
            ->with(AsTenancyLayersProvider::class)
            ->willReturn([TestTenancyLayersProvider::class]);

        $bootstrapper = new TenancyBootstrapper(new TenantContextStore(), $classDiscovery);

        $this->assertInstanceOf(MultilayerTenantResolver::class, $bootstrapper->getResolver());
    }

    #[Test]
    public function falls_back_to_strategy_chain_when_no_layer_providers_are_discovered(): void
    {
        $classDiscovery = $this->createMock(ClassDiscovery::class);
        $classDiscovery->expects($this->once())->method('initialize');
        $classDiscovery->expects($this->once())
            ->method('findClassesWithAttribute')
            ->with(AsTenancyLayersProvider::class)
            ->willReturn([]);

        $bootstrapper = new TenancyBootstrapper(new TenantContextStore(), $classDiscovery);

        $this->assertInstanceOf(TenantResolverChain::class, $bootstrapper->getResolver());
    }

    #[Test]
    public function a_failing_layer_provider_surfaces_a_boot_diagnostic(): void
    {
        // A tenant-layer provider that throws must not vanish: its isolation
        // layer drops from the resolved set, so a silent skip weakens tenant
        // scoping with no signal.
        $diagnostics = \Semitexa\Core\Discovery\BootDiagnostics::begin();
        $classDiscovery = $this->createMock(ClassDiscovery::class);
        $classDiscovery->method('findClassesWithAttribute')
            ->with(AsTenancyLayersProvider::class)
            ->willReturn([ThrowingTenancyLayersProvider::class]);

        new TenancyBootstrapper(new TenantContextStore(), $classDiscovery);

        $warnings = array_filter(
            $diagnostics->getWarnings(),
            static fn ($w): bool => $w->component === 'TenancyBootstrapper',
        );
        $this->assertNotSame([], $warnings, 'A failing tenant-layer provider must surface a diagnostic.');
        $this->assertStringContainsString('isolation layer is absent', reset($warnings)->message);
    }

    #[Test]
    public function a_provider_without_a_layers_method_surfaces_a_boot_diagnostic(): void
    {
        $diagnostics = \Semitexa\Core\Discovery\BootDiagnostics::begin();
        $classDiscovery = $this->createMock(ClassDiscovery::class);
        $classDiscovery->method('findClassesWithAttribute')
            ->with(AsTenancyLayersProvider::class)
            ->willReturn([MalformedTenancyLayersProvider::class]);

        new TenancyBootstrapper(new TenantContextStore(), $classDiscovery);

        $warnings = array_filter(
            $diagnostics->getWarnings(),
            static fn ($w): bool => $w->component === 'TenancyBootstrapper',
        );
        $this->assertNotSame([], $warnings, 'A provider without layers() must surface a diagnostic.');
        $this->assertStringContainsString('no layers() method', reset($warnings)->message);
    }
}

#[AsTenancyLayersProvider]
final class ThrowingTenancyLayersProvider
{
    /** @return list<LayerDefinition> */
    public function layers(): array
    {
        throw new \RuntimeException('layer provider boom');
    }
}

#[AsTenancyLayersProvider]
final class MalformedTenancyLayersProvider
{
    // Deliberately no layers() method.
}

#[AsTenancyLayersProvider]
final class TestTenancyLayersProvider
{
    /** @return list<LayerDefinition> */
    public function layers(): array
    {
        return [
            new LayerDefinition(
                layer: new TestTenantLayer(),
                strategy: new class () {
                    public function resolve(mixed $request): null
                    {
                        return null;
                    }
                },
            ),
        ];
    }
}

final class TestTenantLayer implements TenantLayerInterface
{
    public function id(): string
    {
        return 'test';
    }

    public function defaultValue(): TenantLayerValueInterface
    {
        throw new \LogicException('Not needed in this test.');
    }
}
