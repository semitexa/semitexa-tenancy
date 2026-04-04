<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Definition\LayerDefinition;
use Semitexa\Tenancy\Resolution\MultilayerTenantResolver;
use Semitexa\Tenancy\Resolution\TenantResolverChain;
use Semitexa\Tenancy\TenancyBootstrapper;

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
        $bootstrapper = new TenancyBootstrapper();

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

        $bootstrapper = new TenancyBootstrapper($classDiscovery);

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

        $bootstrapper = new TenancyBootstrapper($classDiscovery);

        $this->assertInstanceOf(TenantResolverChain::class, $bootstrapper->getResolver());
    }
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
