<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Event;

use Semitexa\Tenancy\Context\TenantContext;

final readonly class TenantNotFound
{
    public function __construct(
        public TenantContext $context,
    ) {}
}
