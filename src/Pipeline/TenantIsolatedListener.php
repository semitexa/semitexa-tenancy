<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Pipeline;

use Semitexa\Core\Attribute\AsPipelineListener;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Exception\AccessDeniedException;
use Semitexa\Core\Pipeline\AuthCheck;
use Semitexa\Core\Pipeline\PipelineListenerInterface;
use Semitexa\Core\Pipeline\RequestPipelineContext;
use Semitexa\Core\Tenant\TenantContextAccess;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Attribute\TenantIsolated;

/**
 * Enforces #[TenantIsolated]: a payload or a handler that carries it runs only
 * with a tenant resolved. The attribute promised this for months while only the
 * project graph read it, so a route marked isolated answered with no tenant at all.
 */
#[AsPipelineListener(phase: AuthCheck::class, priority: 1)]
final class TenantIsolatedListener implements PipelineListenerInterface
{
    #[InjectAsReadonly]
    protected TenantContextStoreInterface $tenants;

    public function handle(RequestPipelineContext $context): void
    {
        if (self::isolated($context) && TenantContextAccess::isDefault($this->tenants->tryGet())) {
            throw new AccessDeniedException('This route needs a tenant.');
        }
    }

    private static function isolated(RequestPipelineContext $context): bool
    {
        // Up the class chain: a payload with payload parts arrives as a generated subclass.
        for ($class = new \ReflectionClass($context->requestDto); $class !== false; $class = $class->getParentClass()) {
            if ($class->getAttributes(TenantIsolated::class) !== []) {
                return true;
            }
        }
        foreach ($context->route->handlers as $handler) {
            $name = $handler['class'] ?? null;
            if (is_string($name) && class_exists($name) && (new \ReflectionClass($name))->getAttributes(TenantIsolated::class) !== []) {
                return true;
            }
        }

        return false;
    }
}
