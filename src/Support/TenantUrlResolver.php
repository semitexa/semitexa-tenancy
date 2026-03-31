<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Support;

use Semitexa\Core\Environment;

final class TenantUrlResolver
{
    public static function resolveBaseUrl(string $tenantId, ?bool $preferPublic = null): ?string
    {
        $normalizedTenantId = strtoupper(preg_replace('/[^A-Z0-9_]+/i', '_', $tenantId) ?? $tenantId);
        $usePublicDomain = $preferPublic ?? self::isProductionEnvironment();

        $host = $usePublicDomain
            ? self::readFirstHost("TENANT_{$normalizedTenantId}_PUBLIC_DOMAIN", "TENANT_{$normalizedTenantId}_PUBLIC_DOMAINS")
            : self::readFirstHost("TENANT_{$normalizedTenantId}_DOMAIN", "TENANT_{$normalizedTenantId}_DOMAINS");

        if ($host === null) {
            $host = $usePublicDomain
                ? self::readFirstHost("TENANT_{$normalizedTenantId}_DOMAIN", "TENANT_{$normalizedTenantId}_DOMAINS")
                : self::readFirstHost("TENANT_{$normalizedTenantId}_PUBLIC_DOMAIN", "TENANT_{$normalizedTenantId}_PUBLIC_DOMAINS");
        }

        if ($host === null) {
            return null;
        }

        $scheme = $usePublicDomain ? 'https' : 'http';

        return sprintf('%s://%s', $scheme, $host);
    }

    public static function resolveUrl(string $tenantId, string $path = '/', ?bool $preferPublic = null): ?string
    {
        $baseUrl = self::resolveBaseUrl($tenantId, $preferPublic);
        if ($baseUrl === null) {
            return null;
        }

        $normalizedPath = '/' . ltrim($path, '/');

        return rtrim($baseUrl, '/') . $normalizedPath;
    }

    private static function isProductionEnvironment(): bool
    {
        $appEnv = strtolower((string) (Environment::getEnvValue('APP_ENV', 'prod') ?? 'prod'));

        return in_array($appEnv, ['prod', 'production'], true);
    }

    private static function readFirstHost(string $singleKey, string $listKey): ?string
    {
        $singleHost = trim((string) (Environment::getEnvValue($singleKey) ?? ''));
        if ($singleHost !== '') {
            return $singleHost;
        }

        $list = trim((string) (Environment::getEnvValue($listKey) ?? ''));
        if ($list === '') {
            return null;
        }

        foreach (explode(',', $list) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
