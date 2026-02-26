<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Provider;

use Semitexa\Tenancy\Attribute\AsTenancyLayersProvider;
use Semitexa\Tenancy\Definition\LayerDefinition;
use Semitexa\Core\Tenant\Layer\OrganizationLayer;
use Semitexa\Core\Tenant\Layer\LocaleLayer;
use Semitexa\Core\Tenant\Layer\EnvironmentLayer;
use Semitexa\Tenancy\Resolution\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Resolution\Strategy\PathStrategy;
use Semitexa\Tenancy\Resolution\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Strategy\OrganizationStrategy;
use Semitexa\Tenancy\Strategy\LocaleStrategy;
use Semitexa\Tenancy\Strategy\EnvironmentStrategy;

#[AsTenancyLayersProvider]
class TenancyLayersProvider
{
    public function layers(): array
    {
        return [
            new LayerDefinition(
                layer: new OrganizationLayer(),
                strategy: new OrganizationStrategy(
                    new SubdomainStrategy(baseDomain: $this->getBaseDomain())
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
        return getenv('TENANCY_BASE_DOMAIN') ?: $_ENV['TENANCY_BASE_DOMAIN'] ?? '';
    }

    private function getLocalePrefixes(): array
    {
        $prefixes = getenv('TENANCY_LOCALE_PREFIXES') ?: $_ENV['TENANCY_LOCALE_PREFIXES'] ?? '';
        
        if ($prefixes === '') {
            return ['en', 'uk', 'de', 'pl', 'ru'];
        }
        
        return array_filter(array_map('trim', explode(',', $prefixes)));
    }
}
