<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Identification;

interface TenantRepositoryInterface
{
    public function find(string $id): ?Tenant;

    public function exists(string $id): bool;

    public function findActive(string $id): ?Tenant;

    /** @return Tenant[] */
    public function findAll(): array;
}
