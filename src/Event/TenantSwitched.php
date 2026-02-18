<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Event;

use Semitexa\Tenancy\Context\TenantContext;

final readonly class TenantSwitched
{
    public function __construct(
        public TenantContext $previous,
        public TenantContext $current,
    ) {}
}
