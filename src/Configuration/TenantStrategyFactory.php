<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Configuration;

use Semitexa\Tenancy\Application\Service\Resolver\Strategy\HeaderStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\PathStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\QueryParamStrategy;
use Semitexa\Tenancy\Application\Service\Resolver\Strategy\SubdomainStrategy;
use Semitexa\Tenancy\Domain\Contract\TenantResolverStrategyInterface;
use Semitexa\Tenancy\Application\Service\Resolver\TenantResolverChain;
use Semitexa\Tenancy\Domain\Contract\TenantResolverInterface;

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

    private function buildStrategy(string $name, TenancyConfiguration $config): ?TenantResolverStrategyInterface
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
