<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Event;

use Semitexa\Core\Tenant\TenantContextInterface;

final readonly class TenantNotFound
{
    public function __construct(
        public TenantContextInterface $context,
    ) {}
}
