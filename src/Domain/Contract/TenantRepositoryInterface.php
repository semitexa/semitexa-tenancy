<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Domain\Contract;

use Semitexa\Tenancy\Domain\Model\Tenant;

interface TenantRepositoryInterface
{
    public function find(string $id): ?Tenant;

    public function exists(string $id): bool;

    public function findActive(string $id): ?Tenant;

    /** @return Tenant[] */
    public function findAll(): array;
}
