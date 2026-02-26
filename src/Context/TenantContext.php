<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Tenancy\Exception\TenantRequiredException;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\OrganizationValue;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\LocaleValue;
use Semitexa\Core\Tenant\Layer\EnvironmentLayer;
use Semitexa\Core\Tenant\Layer\EnvironmentValue;

final class TenantContext implements TenantContextInterface
{
    private static ?self $instance = null;

    private array $layers = [];

    private string $strategy = 'none';
    private ?string $source = null;

    public function __construct(
        TenantLayerValueInterface ...$layers
    ) {
        foreach ($layers as $layer) {
            $this->layers[$layer->layer()->id()] = $layer;
        }
    }

    public function getLayer(TenantLayerInterface $layer): ?TenantLayerValueInterface
    {
        $id = $layer->id();
        
        if (!isset($this->layers[$id])) {
            return $layer->defaultValue();
        }
        
        return $this->layers[$id];
    }

    public function hasLayer(TenantLayerInterface $layer): bool
    {
        return isset($this->layers[$layer->id()]);
    }

    public function setLayer(TenantLayerInterface $layer, TenantLayerValueInterface $value): void
    {
        $this->layers[$layer->id()] = $value;
    }

    public function setLayers(TenantLayerValueInterface ...$layers): void
    {
        foreach ($layers as $layer) {
            $this->layers[$layer->layer()->id()] = $layer;
        }
    }

    public static function default(): self
    {
        return new self(
            new OrganizationValue('default', 'Default Organization'),
            LocaleValue::default(),
            EnvironmentValue::prod(),
        );
    }

    public static function fromQueuePayload(array $payload): self
    {
        $layers = [];
        
        if (isset($payload['tenantId'])) {
            $layers[] = new OrganizationValue($payload['tenantId']);
        }
        
        if (isset($payload['locale'])) {
            $layers[] = LocaleValue::fromCode($payload['locale']) ?? LocaleValue::default();
        }
        
        if (isset($payload['environment'])) {
            $layers[] = EnvironmentValue::fromValue($payload['environment']);
        }

        $context = new self(...$layers);
        $context->strategy = $payload['strategy'] ?? 'queue';
        $context->source = 'queue';
        
        return $context;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function isDefault(): bool
    {
        return empty($this->layers);
    }

    public function requireTenantId(): string
    {
        $org = $this->getLayer(new OrganizationLayer());
        
        if ($org === null || $org->rawValue() === 'default') {
            throw new TenantRequiredException('Tenant ID is required but not set in current context');
        }

        return $org->rawValue();
    }

    public function forSerialization(): ?array
    {
        if ($this->isDefault()) {
            return null;
        }

        $data = [
            'strategy' => $this->strategy,
        ];

        $org = $this->getLayer(new OrganizationLayer());
        if ($org !== null) {
            $data['tenantId'] = $org->rawValue();
        }

        $locale = $this->getLayer(new LocaleLayer());
        if ($locale !== null) {
            $data['locale'] = $locale->rawValue();
        }

        $env = $this->getLayer(new EnvironmentLayer());
        if ($env !== null) {
            $data['environment'] = $env->rawValue();
        }

        return $data;
    }

    public static function get(): ?self
    {
        return self::$instance;
    }

    public static function getOrFail(): self
    {
        return self::$instance ?? throw new TenantRequiredException('No tenant context has been set');
    }

    public static function set(self $context): void
    {
        self::$instance = $context;
    }

    public static function clear(): void
    {
        self::$instance = null;
    }
}
