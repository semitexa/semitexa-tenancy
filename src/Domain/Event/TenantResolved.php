<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Domain\Event;

use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Tenancy\Domain\Model\Tenant;

final readonly class TenantResolved
{
    public function __construct(
        public TenantContextInterface $context,
        public ?Tenant $tenant = null,
    ) {}
}
