<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Configuration;

use Semitexa\Tenancy\Support\EnvReader;

final readonly class TenancyConfiguration
{
    public function __construct(
        public bool $enabled,
        public bool $requireTenant,
        public string $strategy,
        public string $headerName,
        public string $baseDomain,
        public string $pathExcluded,
        public string $queryParam,
        public string $tenantsCompact,
        public string $defaultLocale,
        public string $defaultEnvironment,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            enabled: EnvReader::getBool('TENANCY_ENABLED'),
            requireTenant: EnvReader::getBool('TENANCY_REQUIRED'),
            strategy: EnvReader::get('TENANCY_STRATEGY', 'header'),
            headerName: EnvReader::get('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
            baseDomain: EnvReader::get('TENANCY_BASE_DOMAIN'),
            pathExcluded: EnvReader::get('TENANCY_PATH_EXCLUDED'),
            queryParam: EnvReader::get('TENANCY_QUERY_PARAM', 'tenant'),
            tenantsCompact: EnvReader::get('TENANTS'),
            defaultLocale: EnvReader::get('TENANCY_DEFAULT_LOCALE'),
            defaultEnvironment: EnvReader::get('TENANCY_DEFAULT_ENVIRONMENT'),
        );
    }

    /**
     * @return string[]
     */
    public function getStrategyNames(): array
    {
        return array_filter(
            array_map('trim', explode(',', $this->strategy)),
        );
    }

    /**
     * @return string[]
     */
    public function getExcludedPrefixes(): array
    {
        return array_filter(
            array_map('trim', explode(',', $this->pathExcluded)),
        );
    }
}
