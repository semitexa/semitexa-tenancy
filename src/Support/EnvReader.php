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

        return $value === 'true' || $value === '1';
    }

    /**
     * Scan environment for TENANT_{ID}_NAME / TENANT_{ID}_STATUS patterns.
     *
     * @return array<string, array{name?: string, status?: string}>
     */
    public static function scanDetailedTenants(): array
    {
        $tenants = [];

        $sources = getenv() ?: [];

        foreach ($sources as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'TENANT_') || $key === 'TENANT_STRATEGY' || $key === 'TENANT_HEADER' || $key === 'TENANT_DEFAULT') {
                continue;
            }

            if (preg_match('/^TENANT_([A-Z0-9_]+?)_(NAME|STATUS)$/', $key, $matches)) {
                $id = strtolower($matches[1]);
                $field = strtolower($matches[2]);
                $tenants[$id][$field] = (string) $value;
            }
        }

        return $tenants;
    }
}
