<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Domain\Model\LayerDefinition;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\EnvironmentLayer;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\DomainStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\PathStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\StrategyChain;
use Semitexa\Tenancy\Application\Service\LayerStrategy\OrganizationStrategy;
use Semitexa\Tenancy\Application\Service\LayerStrategy\LocaleStrategy;
use Semitexa\Tenancy\Application\Service\LayerStrategy\EnvironmentStrategy;
use Semitexa\Tenancy\Application\Service\EnvReader;

#[AsTenancyLayersProvider]
class TenancyLayersProvider
{
    public function layers(): array
    {
        return [
            new LayerDefinition(
                layer: new OrganizationLayer(),
                strategy: new OrganizationStrategy(
                    new StrategyChain($this->buildOrganizationStrategies())
                ),
            ),
            new LayerDefinition(
                layer: new LocaleLayer(),
                strategy: new LocaleStrategy(
                    new PathStrategy(prefixes: $this->getLocalePrefixes())
                ),
            ),
            new LayerDefinition(
                layer: new EnvironmentLayer(),
                strategy: new EnvironmentStrategy(
                    new HeaderStrategy(headerName: 'X-Environment')
                ),
            ),
        ];
    }

    private function getBaseDomain(): string
    {
        return EnvReader::get('TENANCY_BASE_DOMAIN');
    }

    private function buildDomainStrategy(): ?DomainStrategy
    {
        $map = [];

        foreach (EnvReader::scanDetailedTenants() as $tenantId => $tenant) {
            $domain = trim((string) ($tenant['config']['domain'] ?? ''));
            if ($domain !== '') {
                $map[$domain] = $tenantId;
            }

            foreach (($tenant['config']['domains'] ?? []) as $host) {
                $host = trim((string) $host);
                if ($host !== '') {
                    $map[$host] = $tenantId;
                }
            }

            $publicDomain = trim((string) ($tenant['config']['public_domain'] ?? ''));
            if ($publicDomain !== '') {
                $map[$publicDomain] = $tenantId;
            }

            foreach (($tenant['config']['public_domains'] ?? []) as $host) {
                $host = trim((string) $host);
                if ($host !== '') {
                    $map[$host] = $tenantId;
                }
            }
        }

        return $map === [] ? null : new DomainStrategy($map);
    }

    private function getLocalePrefixes(): array
    {
        $prefixes = EnvReader::get('TENANCY_LOCALE_PREFIXES');

        if ($prefixes === '') {
            return ['en', 'uk', 'de', 'pl', 'ru'];
        }

        return array_filter(array_map('trim', explode(',', $prefixes)));
    }

    /**
     * @return list<\Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface>
     */
    private function buildOrganizationStrategies(): array
    {
        $strategies = [];
        $domainStrategy = $this->buildDomainStrategy();

        if ($domainStrategy !== null) {
            $strategies[] = $domainStrategy;
        }

        $strategies[] = new SubdomainStrategy(baseDomain: $this->getBaseDomain());

        return $strategies;
    }
}
