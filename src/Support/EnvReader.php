<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Support;

final class EnvReader
{
    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, '');

        if ($value === '') {
            return $default;
        }

        return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Scan environment for TENANT_{ID}_NAME / STATUS / DOMAIN / DOMAINS / PUBLIC_DOMAIN / PUBLIC_DOMAINS patterns.
     *
     * @return array<string, array{name?: string, status?: string, config?: array{domain?: string, domains?: list<string>, public_domain?: string, public_domains?: list<string>}}>
     */
    public static function scanDetailedTenants(): array
    {
        /** @var array<string, array{name?: string, status?: string, config?: array{domain?: string, domains?: list<string>, public_domain?: string, public_domains?: list<string>}}> $tenants */
        $tenants = [];

        $sources = getenv() ?: [];

        foreach ($sources as $key => $value) {
            if (!str_starts_with($key, 'TENANT_') || $key === 'TENANT_STRATEGY' || $key === 'TENANT_HEADER' || $key === 'TENANT_DEFAULT') {
                continue;
            }

            if (preg_match('/^TENANT_([A-Z0-9_]+?)_(NAME|STATUS|DOMAIN|DOMAINS|PUBLIC_DOMAIN|PUBLIC_DOMAINS)$/', $key, $matches)) {
                $id = strtolower($matches[1]);
                $field = strtolower($matches[2]);

                if (!isset($tenants[$id])) {
                    $tenants[$id] = [];
                }

                /** @var array{domain?: string, domains?: list<string>, public_domain?: string, public_domains?: list<string>} $config */
                $config = $tenants[$id]['config'] ?? [];

                if ($field === 'domain') {
                    $config['domain'] = trim((string) $value);
                    $tenants[$id]['config'] = $config;
                    continue;
                }

                if ($field === 'domains') {
                    $domains = array_values(array_filter(array_map(
                        static fn (string $item): string => trim($item),
                        explode(',', (string) $value),
                    )));

                    $config['domains'] = $domains;
                    $tenants[$id]['config'] = $config;
                    continue;
                }

                if ($field === 'public_domain') {
                    $config['public_domain'] = trim((string) $value);
                    $tenants[$id]['config'] = $config;
                    continue;
                }

                if ($field === 'public_domains') {
                    $domains = array_values(array_filter(array_map(
                        static fn (string $item): string => trim($item),
                        explode(',', (string) $value),
                    )));

                    $config['public_domains'] = $domains;
                    $tenants[$id]['config'] = $config;
                    continue;
                }

                $tenants[$id][$field] = (string) $value;
            }
        }

        /** @var array<string, array{name?: string, status?: string, config?: array{domain?: string, domains?: list<string>, public_domain?: string, public_domains?: list<string>}}> $tenants */
        return $tenants;
    }
}
