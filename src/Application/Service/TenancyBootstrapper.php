<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Tenancy\Domain\Model\Tenant;

use Semitexa\Core\Discovery\BootDiagnostics;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Event\EventDispatcherInterface;
use Semitexa\Core\HttpResponse;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Core\Tenant\TenancyBootstrapperInterface;
use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Domain\Model\LayerDefinition;
use Semitexa\Tenancy\Application\Service\TenantResolverHandler;
use Semitexa\Tenancy\Application\Service\ConfigTenantRepository;
use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;
use Semitexa\Tenancy\Application\Service\Resolver\MultilayerTenantResolver;
use Semitexa\Tenancy\Application\Service\Resolver\TenantResolverChain;
use Semitexa\Tenancy\Domain\Contract\TenantResolverInterface;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\PathStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\QueryParamStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;
use Semitexa\Tenancy\Application\Service\EnvReader;

/**
 * Builds the tenancy handler from environment configuration.
 *
 * Reads TENANCY_* and TENANTS* environment variables and constructs
 * the full resolution chain, repository, and handler.
 */
final class TenancyBootstrapper implements TenancyBootstrapperInterface
{
    /** @var list<LayerDefinition>|null Cached discovery result (worker-scoped) */
    private static ?array $discoveredLayerDefinitions = null;

    private ClassDiscovery $classDiscovery;
    private TenantResolverHandler $handler;
    private TenantResolverInterface $resolver;
    private TenantRepositoryInterface $repository;
    private bool $enabled;

    public function __construct(
        private readonly TenantContextStoreInterface $tenantContextStore,
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
            tenantContextStore: $this->tenantContextStore,
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

    public function resolve(Request $request): ?HttpResponse
    {
        return $this->handler->handle($request);
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
                // A class carrying #[AsTenancyLayersProvider] with no layers()
                // method is a malformed provider, not a benign skip: its
                // intended tenant-scoping layers never register.
                BootDiagnostics::current()->invalidUsage(
                    'TenancyBootstrapper',
                    "#[AsTenancyLayersProvider] {$class} has no layers() method; its tenant-scoping layers are not registered.",
                );
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
            } catch (\Throwable $e) {
                // A tenant-layer provider that fails to construct or enumerate
                // its layers is a tenant-ISOLATION defect, not a best-effort
                // skip: the layer silently drops out of the resolved set, so
                // requests resolve against a weaker tenant scope with no
                // signal. Surface it through BootDiagnostics (the same channel
                // the pipeline/event/service-contract registries use) instead
                // of vanishing.
                BootDiagnostics::current()->invalidUsage(
                    'TenancyBootstrapper',
                    "tenant-layer provider {$class} failed; its isolation layer is absent from the resolved set: " . $e->getMessage(),
                    $e,
                );
                continue;
            }
        }

        self::$discoveredLayerDefinitions = $definitions;
        return $definitions;
    }

    private function buildStrategy(string $name): ?TenantResolverStrategyInterface
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
        return self::repositoryFromEnvironment();
    }

    /**
     * The environment-configured tenant repository (TENANTS compact string
     * and/or TENANT_{ID}_* detail vars), buildable without standing up the
     * full resolver/handler chain. {@see EnvironmentTenantRepository} exposes
     * this through the DI container.
     */
    public static function repositoryFromEnvironment(): TenantRepositoryInterface
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
