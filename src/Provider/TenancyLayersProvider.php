<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Provider;

use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Definition\LayerDefinition;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\EnvironmentLayer;
use Semitexa\Tenancy\Resolution\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Resolution\Strategy\DomainStrategy;
use Semitexa\Tenancy\Resolution\Strategy\PathStrategy;
use Semitexa\Tenancy\Resolution\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Resolution\Strategy\StrategyChain;
use Semitexa\Tenancy\Strategy\OrganizationStrategy;
use Semitexa\Tenancy\Strategy\LocaleStrategy;
use Semitexa\Tenancy\Strategy\EnvironmentStrategy;
use Semitexa\Tenancy\Support\EnvReader;

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
     * @return list<\Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy>
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
