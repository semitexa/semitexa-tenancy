<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Semitexa\Core\Support\TenantModuleScopeResolver;
use Semitexa\Tenancy\Context\CoroutineContextStore;
use Semitexa\Tenancy\Context\TenantContext;

final class TenantModuleScopeResolverTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'TENANT_SEMITEXA_MODULES' => getenv('TENANT_SEMITEXA_MODULES'),
            'TENANT_OS_MODULES' => getenv('TENANT_OS_MODULES'),
            'TENANT_PLATFORM_MODULES' => getenv('TENANT_PLATFORM_MODULES'),
            'TENANT_DEMO_MODULES' => getenv('TENANT_DEMO_MODULES'),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }

            putenv(sprintf('%s=%s', $key, $value));
        }

        CoroutineContextStore::clearFallback();
    }

    public function test_scopes_for_module_reads_tenant_module_map(): void
    {
        putenv('TENANT_OS_MODULES=semitexa-os-site');
        putenv('TENANT_PLATFORM_MODULES=semitexa-platform-site');

        $this->assertSame(['os'], TenantModuleScopeResolver::scopesForModule('semitexa-os-site'));
        $this->assertSame(['os'], TenantModuleScopeResolver::scopesForModule('os-site'));
        $this->assertSame(['os'], TenantModuleScopeResolver::scopesForModule('semitexa/os-site'));
        $this->assertSame(['platform'], TenantModuleScopeResolver::scopesForModule('semitexa-platform-site'));
        $this->assertSame(['platform'], TenantModuleScopeResolver::scopesForModule('platform-site'));
        $this->assertSame([], TenantModuleScopeResolver::scopesForModule('semitexa-ssr'));
    }

    public function test_select_routes_for_current_tenant_prefers_tenant_scoped_route(): void
    {
        CoroutineContextStore::setFallback(TenantContext::fromResolution('os', 'domain', 'os.semitexa.test'));

        $selected = TenantModuleScopeResolver::selectRoutesForCurrentTenant([
            ['path' => '/', 'module' => 'semitexa-site', 'tenantScopes' => ['semitexa']],
            ['path' => '/', 'module' => 'semitexa-os-site', 'tenantScopes' => ['os']],
            ['path' => '/', 'module' => 'semitexa-ssr', 'tenantScopes' => []],
        ]);

        $this->assertCount(1, $selected);
        $this->assertSame('semitexa-os-site', $selected[0]['module']);
    }

    public function test_select_routes_for_current_tenant_falls_back_to_shared_routes(): void
    {
        CoroutineContextStore::setFallback(TenantContext::fromResolution('platform', 'domain', 'platform.semitexa.test'));

        $selected = TenantModuleScopeResolver::selectRoutesForCurrentTenant([
            ['path' => '/robots.txt', 'module' => 'semitexa-ssr', 'tenantScopes' => []],
        ]);

        $this->assertCount(1, $selected);
        $this->assertSame('semitexa-ssr', $selected[0]['module']);
    }
}
