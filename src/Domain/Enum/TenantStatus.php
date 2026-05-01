<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Domain\Enum;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    public static function normalize(self|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null) {
            return self::Active;
        }

        return self::from(strtolower(trim($value)));
    }
}
