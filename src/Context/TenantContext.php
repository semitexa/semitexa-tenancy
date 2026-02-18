<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Tenancy\Exception\TenantRequiredException;

readonly class TenantContext
{
    public function __construct(
        public string $tenantId,
        public string $strategy,
        public ?string $source = null,
    ) {}

    public static function default(): self
    {
        return new self(
            tenantId: 'default',
            strategy: 'none',
            source: null,
        );
    }

    public static function fromQueuePayload(array $payload): self
    {
        return new self(
            tenantId: $payload['tenantId'],
            strategy: $payload['strategy'] ?? 'queue',
            source: 'queue',
        );
    }

    public function isDefault(): bool
    {
        return $this->tenantId === 'default';
    }

    public function requireTenantId(): string
    {
        if ($this->isDefault()) {
            throw new TenantRequiredException('Tenant ID is required but not set in current context');
        }

        return $this->tenantId;
    }

    /**
     * Serialize for queue propagation. Returns null for default context (per A4).
     *
     * @return array{tenantId: string, strategy: string}|null
     */
    public function forSerialization(): ?array
    {
        if ($this->isDefault()) {
            return null;
        }

        return [
            'tenantId' => $this->tenantId,
            'strategy' => $this->strategy,
        ];
    }
}
