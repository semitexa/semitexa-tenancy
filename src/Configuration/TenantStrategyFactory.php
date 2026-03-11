<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Configuration;

use Semitexa\Tenancy\Resolution\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Resolution\Strategy\PathStrategy;
use Semitexa\Tenancy\Resolution\Strategy\QueryParamStrategy;
use Semitexa\Tenancy\Resolution\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Resolution\Strategy\TenantResolverStrategy;
use Semitexa\Tenancy\Resolution\TenantResolverChain;
use Semitexa\Tenancy\Resolution\TenantResolverInterface;

final class TenantStrategyFactory
{
    public function build(TenancyConfiguration $config): TenantResolverInterface
    {
        $strategies = [];

        foreach ($config->getStrategyNames() as $name) {
            $strategy = $this->buildStrategy($name, $config);

            if ($strategy !== null) {
                $strategies[] = $strategy;
            }
        }

        return new TenantResolverChain($strategies);
    }

    private function buildStrategy(string $name, TenancyConfiguration $config): ?TenantResolverStrategy
    {
        return match ($name) {
            'header' => new HeaderStrategy(headerName: $config->headerName),
            'subdomain' => $config->baseDomain !== ''
                ? new SubdomainStrategy(baseDomain: $config->baseDomain)
                : null,
            'path' => new PathStrategy(excludedPrefixes: $config->getExcludedPrefixes()),
            'query' => new QueryParamStrategy(paramName: $config->queryParam),
            default => null,
        };
    }
}
