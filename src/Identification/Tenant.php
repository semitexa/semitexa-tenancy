<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Identification;

readonly class Tenant
{
    public function __construct(
        public string $id,
        public string $name,
        public TenantStatus $status,
        public array $config = [],
    ) {}

    public static function create(string $id, string $name, TenantStatus|string|null $status = null, array $config = []): self
    {
        return new self(
            id: $id,
            name: $name,
            status: TenantStatus::normalize($status),
            config: $config,
        );
    }
}
