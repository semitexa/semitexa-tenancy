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

/**
 * Core asks this factory for a bootstrapper on every request
 * (Application::__construct). A TenancyBootstrapper is immutable once built:
 * its resolver/strategies/repository are derived from the environment, which
 * does not change inside a running worker, and its only collaborators are the
 * worker-scoped context store, event dispatcher and class discovery. So the
 * factory builds it once and hands the same instance back for as long as the
 * collaborators it was built from are the ones the container returns —
 * instead of re-scanning getenv() and re-wiring the chain per request.
 *
 * The memo lives on this (container-managed) factory instance, not in static
 * state: a fresh container — e.g. a test that changed TENANT_* env vars and
 * boots a new one — gets a fresh factory and re-reads the environment.
 */
#[SatisfiesServiceContract(of: TenancyBootstrapperFactoryInterface::class)]
final class TenancyBootstrapperFactory implements TenancyBootstrapperFactoryInterface
{
    private ?TenancyBootstrapper $built = null;

    /** @var array{0: TenantContextStoreInterface, 1: ?EventDispatcherInterface, 2: ?ClassDiscovery}|null */
    private ?array $builtFrom = null;

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
        $from = [$tenantContextStore, $events, $classDiscovery];
        if ($this->built !== null && $this->builtFrom !== null
            && $this->builtFrom[0] === $from[0]
            && $this->builtFrom[1] === $from[1]
            && $this->builtFrom[2] === $from[2]
        ) {
            return $this->built;
        }

        $bootstrapper = new TenancyBootstrapper(
            tenantContextStore: $tenantContextStore,
            classDiscovery: $classDiscovery,
            events: $events,
        );
        $this->built = $bootstrapper;
        $this->builtFrom = $from;

        return $bootstrapper;
    }
}
