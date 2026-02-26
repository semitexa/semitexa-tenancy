<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsTenantLayerStrategy
{
    public function __construct(
        public readonly string $layerId,
    ) {}
}
