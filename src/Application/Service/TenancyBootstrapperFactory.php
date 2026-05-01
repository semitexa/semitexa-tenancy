<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Psr\Container\ContainerInterface;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Core\Tenant\TenancyBootstrapperFactoryInterface;
use Semitexa\Core\Tenant\TenancyBootstrapperInterface;

#[SatisfiesServiceContract(of: TenancyBootstrapperFactoryInterface::class)]
final class TenancyBootstrapperFactory implements TenancyBootstrapperFactoryInterface
{
    public function create(ContainerInterface $container): TenancyBootstrapperInterface
    {
        $classDiscovery = $container->has(ClassDiscovery::class)
            ? $container->get(ClassDiscovery::class)
            : null;

        $events = $container->has(EventDispatcherInterface::class)
            ? $container->get(EventDispatcherInterface::class)
            : null;

        /** @var TenantContextStoreInterface $tenantContextStore */
        $tenantContextStore = $container->get(TenantContextStoreInterface::class);

        /** @var ClassDiscovery|null $classDiscovery */
        /** @var EventDispatcherInterface|null $events */
        return new TenancyBootstrapper(
            tenantContextStore: $tenantContextStore,
            classDiscovery: $classDiscovery,
            events: $events,
        );
    }
}
