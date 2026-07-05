<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Application\Service;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\HttpResponse;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\TenancyBootstrapperInterface;
use Semitexa\Tenancy\Context\TenantContextStore;

/**
 * Container-managed face of the tenancy bootstrapper — the sibling of
 * {@see EnvironmentTenantRepository}, one interface up.
 *
 * `TenancyBootstrapper` builds its full resolver/handler chain in the
 * constructor, so it cannot be container-managed itself — nothing satisfied
 * `TenancyBootstrapperInterface` in DI. That hole was live-visible in the SSE
 * re-run path: `RouteExecutor::establishReRunExecutionContext()` resolves the
 * bootstrapper from the container to know whether tenancy is enabled, got
 * nothing, and `SessionPhase` then CLEARED the freshly restored tenant
 * context — a demo tenant's held-open stream re-ran as 'default' and served
 * the wrong tenant's rows. This adapter is instantiable with no arguments and
 * lazily delegates, so `$container->get(TenancyBootstrapperInterface::class)`
 * finally works.
 */
#[SatisfiesServiceContract(of: TenancyBootstrapperInterface::class)]
final class ContainerTenancyBootstrapper implements TenancyBootstrapperInterface
{
    private ?TenancyBootstrapper $inner = null;

    public function isEnabled(): bool
    {
        return $this->inner()->isEnabled();
    }

    public function resolve(Request $request): ?HttpResponse
    {
        return $this->inner()->resolve($request);
    }

    private function inner(): TenancyBootstrapper
    {
        return $this->inner ??= new TenancyBootstrapper(TenantContextStore::shared());
    }
}
