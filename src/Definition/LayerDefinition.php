<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Definition;

use Semitexa\Core\Tenant\Layer\TenantLayerInterface;

final readonly class LayerDefinition
{
    public function __construct(
        public TenantLayerInterface $layer,
        public object $strategy,
    ) {}
}
