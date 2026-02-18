<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Event;

use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Identification\Tenant;

final readonly class TenantResolved
{
    public function __construct(
        public TenantContext $context,
        public ?Tenant $tenant = null,
    ) {}
}
