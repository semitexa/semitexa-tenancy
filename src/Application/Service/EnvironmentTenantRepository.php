<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;
use Semitexa\Tenancy\Domain\Model\Tenant;

/**
 * Container-managed face of the tenant repository.
 *
 * `ConfigTenantRepository` needs a config array in its constructor, so it
 * cannot be container-managed itself — until now nothing satisfied
 * `TenantRepositoryInterface` in DI and every consumer either built a full
 * `TenancyBootstrapper` by hand or (like scheduler:plan) degraded with a
 * "could not be resolved" warning. This adapter is instantiable with no
 * arguments and lazily delegates to the environment-configured repository,
 * so `$container->get(TenantRepositoryInterface::class)` finally works.
 */
#[SatisfiesRepositoryContract(of: TenantRepositoryInterface::class)]
final class EnvironmentTenantRepository implements TenantRepositoryInterface
{
    private ?TenantRepositoryInterface $inner = null;

    public function find(string $id): ?Tenant
    {
        return $this->inner()->find($id);
    }

    public function exists(string $id): bool
    {
        return $this->inner()->exists($id);
    }

    public function findActive(string $id): ?Tenant
    {
        return $this->inner()->findActive($id);
    }

    /** @return array<Tenant> */
    public function findAll(): array
    {
        return $this->inner()->findAll();
    }

    private function inner(): TenantRepositoryInterface
    {
        return $this->inner ??= TenancyBootstrapper::repositoryFromEnvironment();
    }
}
