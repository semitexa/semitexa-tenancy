<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Tenancy\Domain\Enum\TenantStatus;

use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;

use Semitexa\Tenancy\Domain\Model\Tenant;

/**
 * Config-based tenant repository.
 *
 * Accepts two config formats:
 *
 * 1. Compact string: "id:name:status,id:name:status,..."
 *    Passed as $config['tenants_compact'].
 *
 * 2. Detailed array: ['id' => ['name' => '...', 'status' => '...', 'config' => [...]]]
 *    Passed as $config['tenants'].
 */
final class ConfigTenantRepository implements TenantRepositoryInterface
{
    /** @var array<string, Tenant> */
    private array $tenants = [];

    public function __construct(array $config)
    {
        if (isset($config['tenants_compact']) && $config['tenants_compact'] !== '') {
            $this->parseCompact($config['tenants_compact']);
        }

        if (isset($config['tenants']) && is_array($config['tenants'])) {
            foreach ($config['tenants'] as $id => $data) {
                $this->tenants[$id] = new Tenant(
                    id: $id,
                    name: $data['name'] ?? $id,
                    status: TenantStatus::normalize($data['status'] ?? null),
                    config: $data['config'] ?? [],
                );
            }
        }
    }

    public function find(string $id): ?Tenant
    {
        return $this->tenants[$id] ?? null;
    }

    public function exists(string $id): bool
    {
        return isset($this->tenants[$id]);
    }

    public function findActive(string $id): ?Tenant
    {
        $tenant = $this->find($id);

        return ($tenant !== null && $tenant->status === TenantStatus::Active) ? $tenant : null;
    }

    /** @return Tenant[] */
    public function findAll(): array
    {
        return array_values($this->tenants);
    }

    private function parseCompact(string $value): void
    {
        $entries = explode(',', $value);

        foreach ($entries as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            $parts = explode(':', $entry, 3);
            $id = trim($parts[0]);

            if ($id === '') {
                continue;
            }

            $this->tenants[$id] = new Tenant(
                id: $id,
                name: isset($parts[1]) ? trim($parts[1]) : $id,
                status: TenantStatus::normalize(isset($parts[2]) ? trim($parts[2]) : null),
            );
        }
    }
}
