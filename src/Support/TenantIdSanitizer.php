<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Support;

final class TenantIdSanitizer
{
    public static function sanitize(string $value, int $maxLength = 64): ?string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);

        if ($clean === '') {
            return null;
        }

        return substr($clean, 0, $maxLength);
    }
}
