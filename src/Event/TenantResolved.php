<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Event;

use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Tenancy\Identification\Tenant;

final readonly class TenantResolved
{
    public function __construct(
        public TenantContextInterface $context,
        public ?Tenant $tenant = null,
    ) {}
}
