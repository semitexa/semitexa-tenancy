<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Context;

use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Tenancy\Exception\TenantRequiredException;
use Semitexa\Tenancy\Support\EnvReader;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\OrganizationValue;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\LocaleValue;
use Semitexa\Core\Tenant\Layer\EnvironmentLayer;
use Semitexa\Core\Tenant\Layer\EnvironmentValue;

/**
 * @property-read string $tenantId
 * @property-read string $strategy
 * @property-read ?string $source
 */
final class TenantContext implements TenantContextInterface
{
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
        $locale = EnvReader::get('TENANCY_DEFAULT_LOCALE');
        $environment = EnvReader::get('TENANCY_DEFAULT_ENVIRONMENT');

        return new self(
            new OrganizationValue('default', 'Default Organization'),
            $locale !== '' ? LocaleValue::fromCode($locale) : LocaleValue::default(),
            $environment !== '' ? EnvironmentValue::fromValue($environment) : EnvironmentValue::prod(),
        );
    }

    /**
     * Create context from single-tenant resolution (env-based strategies).
     * Use this when resolving tenant from header/path/subdomain/query.
     */
    public static function fromResolution(string $tenantId, string $strategy, ?string $source = null): self
    {
        $context = new self(new OrganizationValue($tenantId));
        $context->strategy = $strategy;
        $context->source = $source ?? $strategy;
        return $context;
    }

    /**
     * Organization layer value (tenant id) for backward compatibility.
     * Prefer getLayer(OrganizationLayer())->rawValue() in new code.
     */
    public function getTenantId(): string
    {
        $org = $this->getLayer(new OrganizationLayer());
        return $org !== null ? $org->rawValue() : 'default';
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'tenantId' => $this->getTenantId(),
            'strategy' => $this->strategy,
            'source' => $this->source,
            default => throw new \InvalidArgumentException("Unknown property: {$name}"),
        };
    }

    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'tenantId', 'strategy', 'source' => throw new \LogicException("Property {$name} is read-only."),
            default => throw new \InvalidArgumentException("Unknown property: {$name}"),
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'tenantId', 'strategy' => true,
            'source' => $this->source !== null,
            default => false,
        };
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
        $orgLayer = new OrganizationLayer();
        if (!$this->hasLayer($orgLayer)) {
            return true;
        }
        return $this->getLayer($orgLayer)->rawValue() === 'default';
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

        $data = [];

        $orgLayer = new OrganizationLayer();
        if ($this->hasLayer($orgLayer)) {
            $data['tenantId'] = $this->getLayer($orgLayer)->rawValue();
        }

        $data['strategy'] = $this->strategy;

        $localeLayer = new LocaleLayer();
        if ($this->hasLayer($localeLayer)) {
            $data['locale'] = $this->getLayer($localeLayer)->rawValue();
        }

        $envLayer = new EnvironmentLayer();
        if ($this->hasLayer($envLayer)) {
            $data['environment'] = $this->getLayer($envLayer)->rawValue();
        }

        return $data;
    }

    public static function get(): ?self
    {
        $context = TenantContextStore::shared()->tryGet();

        return $context instanceof self ? $context : null;
    }

    public static function getOrFail(): self
    {
        $context = TenantContextStore::shared()->getOrFail();

        if ($context instanceof self) {
            return $context;
        }

        throw new TenantRequiredException('No tenancy tenant context has been set');
    }

    public static function set(self $context): void
    {
        TenantContextStore::shared()->set($context);
    }

    public static function clear(): void
    {
        TenantContextStore::shared()->clear();
    }
}
