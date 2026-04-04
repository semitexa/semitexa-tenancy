<?php

declare(strict_types=1);

namespace Semitexa\Tenancy;

use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Definition\LayerDefinition;
use Semitexa\Tenancy\Handler\TenantResolverHandler;
use Semitexa\Tenancy\Identification\ConfigTenantRepository;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;
use Semitexa\Tenancy\Resolution\MultilayerTenantResolver;
use Semitexa\Tenancy\Resolution\TenantResolverChain;
use Semitexa\Tenancy\Resolution\TenantResolverInterface;
use Semitexa\Tenancy\Resolution\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Resolution\Strategy\PathStrategy;
use Semitexa\Tenancy\Resolution\Strategy\QueryParamStrategy;
use Semitexa\Tenancy\Resolution\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;
use Semitexa\Tenancy\Support\EnvReader;

/**
 * Builds the tenancy handler from environment configuration.
 *
 * Reads TENANCY_* and TENANTS* environment variables and constructs
 * the full resolution chain, repository, and handler.
 */
final class TenancyBootstrapper
{
    /** @var list<LayerDefinition>|null Cached discovery result (worker-scoped) */
    private static ?array $discoveredLayerDefinitions = null;

    private ClassDiscovery $classDiscovery;
    private TenantResolverHandler $handler;
    private TenantResolverInterface $resolver;
    private TenantRepositoryInterface $repository;
    private bool $enabled;

    public function __construct(
        ?ClassDiscovery $classDiscovery = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->classDiscovery = $classDiscovery ?? new ClassDiscovery();
        $this->enabled = EnvReader::getBool('TENANCY_ENABLED');

        $this->repository = $this->buildRepository();
        $this->resolver = $this->buildResolver();
        $this->handler = new TenantResolverHandler(
            resolver: $this->resolver,
            tenants: $this->repository,
            events: $events,
            requireTenant: EnvReader::getBool('TENANCY_REQUIRED'),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getHandler(): TenantResolverHandler
    {
        return $this->handler;
    }

    public function getResolver(): TenantResolverInterface
    {
        return $this->resolver;
    }

    public function getRepository(): TenantRepositoryInterface
    {
        return $this->repository;
    }

    private function buildResolver(): TenantResolverInterface
    {
        $layerDefinitions = $this->discoverLayerDefinitions();
        if ($layerDefinitions !== []) {
            return new MultilayerTenantResolver($layerDefinitions);
        }

        $strategyNames = array_filter(
            array_map('trim', explode(',', EnvReader::get('TENANCY_STRATEGY', 'header'))),
        );

        $strategies = [];

        foreach ($strategyNames as $name) {
            $strategy = $this->buildStrategy($name);

            if ($strategy !== null) {
                $strategies[] = $strategy;
            }
        }

        return new TenantResolverChain($strategies);
    }

    /**
     * Discover TenancyLayersProvider classes and collect all layer definitions.
     *
     * @return list<LayerDefinition>
     */
    /**
     * Discover layer definitions from classes with #[AsTenancyLayersProvider].
     * Uses a static cache so reflection + discovery runs only once per worker.
     *
     * @return list<LayerDefinition>
     */
    private function discoverLayerDefinitions(): array
    {
        if (self::$discoveredLayerDefinitions !== null) {
            return self::$discoveredLayerDefinitions;
        }

        $this->classDiscovery->initialize();
        $providerClasses = $this->classDiscovery->findClassesWithAttribute(AsTenancyLayersProvider::class);
        $definitions = [];

        foreach ($providerClasses as $class) {
            if (!method_exists($class, 'layers')) {
                continue;
            }
            try {
                $provider = new $class();
                $layers = $provider->layers();
                if (is_array($layers)) {
                    foreach ($layers as $def) {
                        if ($def instanceof LayerDefinition) {
                            $definitions[] = $def;
                        }
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        self::$discoveredLayerDefinitions = $definitions;
        return $definitions;
    }

    private function buildStrategy(string $name): ?TenantResolverStrategy
    {
        return match ($name) {
            'header' => new HeaderStrategy(
                headerName: EnvReader::get('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
            ),
            'subdomain' => $this->buildSubdomainStrategy(),
            'path' => new PathStrategy(
                excludedPrefixes: array_filter(
                    array_map('trim', explode(',', EnvReader::get('TENANCY_PATH_EXCLUDED'))),
                ),
            ),
            'query' => new QueryParamStrategy(
                paramName: EnvReader::get('TENANCY_QUERY_PARAM', 'tenant'),
            ),
            default => null,
        };
    }

    private function buildSubdomainStrategy(): ?SubdomainStrategy
    {
        $baseDomain = EnvReader::get('TENANCY_BASE_DOMAIN');

        if ($baseDomain === '') {
            return null;
        }

        return new SubdomainStrategy(baseDomain: $baseDomain);
    }

    private function buildRepository(): TenantRepositoryInterface
    {
        $config = [];

        $compact = EnvReader::get('TENANTS');
        if ($compact !== '') {
            $config['tenants_compact'] = $compact;
        }

        $detailed = EnvReader::scanDetailedTenants();
        if ($detailed !== []) {
            $config['tenants'] = $detailed;
        }

        return new ConfigTenantRepository($config);
    }
}
