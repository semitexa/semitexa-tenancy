<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Application\Service\TenancyBootstrapper;
use Semitexa\Tenancy\Application\Service\TenancyBootstrapperFactory;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Tenancy\Domain\Model\LayerDefinition;

/**
 * Core calls the factory once per request; the bootstrapper it returns is
 * immutable env-derived wiring, so the factory must build it once per worker
 * (per factory instance) and only rebuild when its collaborators change.
 */
final class TenancyBootstrapperFactoryTest extends TestCase
{
    private string|false $savedTenants = false;

    /** @var list<LayerDefinition>|null */
    private ?array $savedLayerDefinitions = null;

    protected function setUp(): void
    {
        // Both are process-global: snapshot them, start each test from an
        // empty discovery cache, and put the originals back in tearDown().
        $this->savedTenants = getenv('TENANTS');
        $this->savedLayerDefinitions = self::layerDefinitions()->getValue();
        self::layerDefinitions()->setValue(null, null);
    }

    protected function tearDown(): void
    {
        self::layerDefinitions()->setValue(null, $this->savedLayerDefinitions);
        putenv($this->savedTenants === false ? 'TENANTS' : 'TENANTS=' . $this->savedTenants);
    }

    private static function layerDefinitions(): \ReflectionProperty
    {
        return new \ReflectionProperty(TenancyBootstrapper::class, 'discoveredLayerDefinitions');
    }

    #[Test]
    public function reuses_the_bootstrapper_across_requests(): void
    {
        $container = $this->container(new TenantContextStore(), $this->emptyDiscovery());
        $factory = new TenancyBootstrapperFactory();

        $first = $factory->create($container);

        self::assertSame($first, $factory->create($container));
    }

    #[Test]
    public function rebuilds_when_the_container_hands_out_different_collaborators(): void
    {
        $discovery = $this->emptyDiscovery();
        $factory = new TenancyBootstrapperFactory();

        $first = $factory->create($this->container(new TenantContextStore(), $discovery));
        $second = $factory->create($this->container(new TenantContextStore(), $discovery));

        self::assertNotSame($first, $second);
    }

    #[Test]
    public function a_fresh_factory_rereads_the_environment(): void
    {
        $container = $this->container(new TenantContextStore(), $this->emptyDiscovery());

        putenv('TENANTS=acme:Acme:active');
        $before = (new TenancyBootstrapperFactory())->create($container);
        putenv('TENANTS=acme:Acme:active,globex:Globex:active');
        $after = (new TenancyBootstrapperFactory())->create($container);

        self::assertInstanceOf(TenancyBootstrapper::class, $before);
        self::assertInstanceOf(TenancyBootstrapper::class, $after);
        self::assertFalse($before->getRepository()->exists('globex'));
        self::assertTrue($after->getRepository()->exists('globex'));
    }

    private function emptyDiscovery(): ClassDiscovery
    {
        $discovery = $this->createStub(ClassDiscovery::class);
        $discovery->method('findClassesWithAttribute')->willReturn([]);

        return $discovery;
    }

    private function container(TenantContextStoreInterface $store, ClassDiscovery $discovery): ContainerInterface
    {
        return new class ($store, $discovery) implements ContainerInterface {
            public function __construct(
                private readonly TenantContextStoreInterface $store,
                private readonly ClassDiscovery $discovery,
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    TenantContextStoreInterface::class => $this->store,
                    ClassDiscovery::class => $this->discovery,
                    default => throw new \LogicException("unexpected {$id}"),
                };
            }

            public function has(string $id): bool
            {
                return $id === TenantContextStoreInterface::class || $id === ClassDiscovery::class;
            }
        };
    }
}
