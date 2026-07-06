<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Tenancy\Application\Service\EnvironmentTenantRepository;
use Semitexa\Tenancy\Domain\Contract\TenantRepositoryInterface;
use Semitexa\Tenancy\Domain\Enum\TenantStatus;

/**
 * The container-managed face of the tenant repository: it must be
 * discoverable as the TenantRepositoryInterface contract implementation
 * (this is what un-breaks `$container->get(TenantRepositoryInterface)`
 * for scheduler:plan tenant expansion), instantiable with no arguments,
 * and behave exactly like the environment-configured repository.
 */
final class EnvironmentTenantRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TENANTS');
    }

    #[Test]
    public function satisfies_the_tenant_repository_contract_for_di(): void
    {
        $reflection = new \ReflectionClass(EnvironmentTenantRepository::class);

        $attributes = $reflection->getAttributes(SatisfiesRepositoryContract::class);
        self::assertCount(1, $attributes, 'Must declare the DI contract binding.');
        self::assertSame(TenantRepositoryInterface::class, $attributes[0]->newInstance()->of);

        self::assertTrue($reflection->implementsInterface(TenantRepositoryInterface::class));
        self::assertTrue(
            $reflection->getConstructor() === null
            || $reflection->getConstructor()->getNumberOfRequiredParameters() === 0,
            'Container-managed services need a parameterless constructor.',
        );
    }

    #[Test]
    public function delegates_to_the_environment_configured_repository(): void
    {
        putenv('TENANTS=acme:Acme:active,dormant:Dormant:suspended');

        $repository = new EnvironmentTenantRepository();

        $all = $repository->findAll();
        self::assertCount(2, $all);

        $acme = $repository->find('acme');
        self::assertNotNull($acme);
        self::assertSame('Acme', $acme->name);
        self::assertSame(TenantStatus::Active, $acme->status);

        self::assertTrue($repository->exists('dormant'));
        self::assertNull($repository->findActive('dormant'), 'Suspended tenant must not resolve as active.');
        self::assertNotNull($repository->findActive('acme'));
        self::assertNull($repository->find('ghost'));
    }
}
