<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Tenancy\Application\Service\TenantFanout;
use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;
use Semitexa\Tenancy\Domain\Model\Tenant;

/**
 * TenantFanout runs a unit of work once per ACTIVE tenant, binding that
 * tenant's context around each call so background (context-less) sweeps —
 * the weaver, the task ticker — cover every tenant with correct #[TenantScoped]
 * isolation. Pins: active-only, context bound during each call, one failure
 * does not abort the sweep, prior context restored.
 */
final class TenantFanoutTest extends TestCase
{
    private function fanout(array $tenants, RecordingContextStore $store): TenantFanout
    {
        $fanout = new TenantFanout();
        (new \ReflectionProperty(TenantFanout::class, 'tenants'))->setValue($fanout, new StubTenantRepository($tenants));
        (new \ReflectionProperty(TenantFanout::class, 'contextStore'))->setValue($fanout, $store);

        return $fanout;
    }

    #[Test]
    public function it_runs_once_per_active_tenant_binding_each_context(): void
    {
        $store = new RecordingContextStore();
        $fanout = $this->fanout([
            Tenant::create('acme', 'Acme', TenantStatus::Active),
            Tenant::create('globex', 'Globex', TenantStatus::Active),
            Tenant::create('dormant', 'Dormant', TenantStatus::Suspended),
        ], $store);

        $seen = [];
        $fanout->eachTenant(function (string $tenantId) use ($store, &$seen): void {
            // The tenant's context is bound WHILE the work runs.
            $seen[] = [$tenantId, $store->tryGet()?->getTenantId()];
        });

        self::assertSame(
            [['acme', 'acme'], ['globex', 'globex']],
            $seen,
            'Runs for active tenants only, each under its own bound context.',
        );
        self::assertNull($store->tryGet(), 'Context is cleared after the sweep (no prior context to restore).');
    }

    #[Test]
    public function one_tenant_failure_does_not_abort_the_sweep(): void
    {
        $store = new RecordingContextStore();
        $fanout = $this->fanout([
            Tenant::create('acme', 'Acme', TenantStatus::Active),
            Tenant::create('globex', 'Globex', TenantStatus::Active),
        ], $store);

        $reached = [];
        $fanout->eachTenant(function (string $tenantId) use (&$reached): void {
            $reached[] = $tenantId;
            if ($tenantId === 'acme') {
                throw new \RuntimeException('acme blew up');
            }
        });

        self::assertSame(['acme', 'globex'], $reached, 'Globex still runs after Acme throws.');
    }

    #[Test]
    public function an_empty_registry_degrades_to_a_single_default_pass(): void
    {
        // Tenancy installed but no tenants configured (TENANTS unset): the sweep
        // must NOT silently do nothing — background weaving/ticking would stop.
        $store = new RecordingContextStore();
        $fanout = $this->fanout([], $store);

        $seen = [];
        $fanout->eachTenant(function (string $tenantId) use (&$seen): void {
            $seen[] = $tenantId;
        });

        self::assertSame(['default'], $seen, 'Empty registry runs once under the default sentinel.');
    }

    #[Test]
    public function only_suspended_or_deleted_tenants_also_degrades_to_default(): void
    {
        $store = new RecordingContextStore();
        $fanout = $this->fanout([
            Tenant::create('dormant', 'Dormant', TenantStatus::Suspended),
        ], $store);

        $seen = [];
        $fanout->eachTenant(function (string $tenantId) use (&$seen): void {
            $seen[] = $tenantId;
        });

        self::assertSame(['default'], $seen, 'No ACTIVE tenants → single default pass, not a no-op.');
    }

    #[Test]
    public function a_prior_context_is_restored_after_the_sweep(): void
    {
        $store = new RecordingContextStore();
        $store->set(new FixedContext('outer'));
        $fanout = $this->fanout([Tenant::create('acme', 'Acme', TenantStatus::Active)], $store);

        $fanout->eachTenant(static function (): void {});

        self::assertSame('outer', $store->tryGet()?->getTenantId(), 'The pre-sweep context is restored.');
    }
}

final class StubTenantRepository implements TenantRepositoryInterface
{
    /** @param Tenant[] $tenants */
    public function __construct(private readonly array $tenants) {}

    public function find(string $id): ?Tenant
    {
        foreach ($this->tenants as $t) {
            if ($t->id === $id) {
                return $t;
            }
        }

        return null;
    }

    public function exists(string $id): bool
    {
        return $this->find($id) !== null;
    }

    public function findActive(string $id): ?Tenant
    {
        $t = $this->find($id);

        return $t !== null && $t->status === TenantStatus::Active ? $t : null;
    }

    /** @return Tenant[] */
    public function findAll(): array
    {
        return $this->tenants;
    }
}

final class RecordingContextStore implements TenantContextStoreInterface
{
    private ?TenantContextInterface $context = null;

    public function get(): TenantContextInterface
    {
        return $this->context ?? throw new \LogicException('no context');
    }

    public function tryGet(): ?TenantContextInterface
    {
        return $this->context;
    }

    public function set(TenantContextInterface $context): void
    {
        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = null;
    }
}

final class FixedContext implements TenantContextInterface
{
    public function __construct(private readonly string $id) {}

    public function getTenantId(): string
    {
        return $this->id;
    }

    public function getLayer(\Semitexa\Core\Tenant\Layer\TenantLayerInterface $layer): ?\Semitexa\Core\Tenant\Layer\TenantLayerValueInterface
    {
        return null;
    }

    public function hasLayer(\Semitexa\Core\Tenant\Layer\TenantLayerInterface $layer): bool
    {
        return false;
    }
}
