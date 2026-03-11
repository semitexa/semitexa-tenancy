<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Configuration;

use Semitexa\Tenancy\Identification\ConfigTenantRepository;
use Semitexa\Tenancy\Identification\TenantRepositoryInterface;
use Semitexa\Tenancy\Support\EnvReader;

final class TenantRepositoryFactory
{
    public function build(TenancyConfiguration $config): TenantRepositoryInterface
    {
        $repoConfig = [];

        if ($config->tenantsCompact !== '') {
            $repoConfig['tenants_compact'] = $config->tenantsCompact;
        }

        $detailed = EnvReader::scanDetailedTenants();
        if ($detailed !== []) {
            $repoConfig['tenants'] = $detailed;
        }

        return new ConfigTenantRepository($repoConfig);
    }
}
