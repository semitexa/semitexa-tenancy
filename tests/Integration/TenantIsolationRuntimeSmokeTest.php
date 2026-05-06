<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Auth\Context\AuthContextStore;
use Semitexa\Authorization\Domain\Model\CapabilityGrantSet;
use Semitexa\Authorization\Domain\Model\PermissionGrantSet;
use Semitexa\Authorization\Domain\Model\SubjectGrantSet;
use Semitexa\Core\Application;
use Semitexa\Core\Lifecycle\CurrentRequestStore;
use Semitexa\Core\Lifecycle\PerRequestStateRegistry;
use Semitexa\Core\Lifecycle\TestStateResetRegistry;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\TenantContextStoreInterface;
use Semitexa\Locale\Context\LocaleContextStore;
use Semitexa\Modules\AuthDemo\Application\Service\AuthDemoStubAuthHandler;
use Semitexa\Modules\AuthDemo\Domain\Model\AuthDemoUser;
use Semitexa\Rbac\Application\Service\RbacDecisionCache;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Context\TenantContextStore;

/**
 * Tenant / locale / context isolation runtime smoke.
 *
 * Tests the full set of contextual state surfaces that could leak between
 * sequential requests handled by the same Application instance:
 *
 *   - TenantContextStore  (tenancy)
 *   - LocaleContextStore  (locale)
 *   - AuthContextStore    (auth)
 *   - CurrentRequestStore (request)
 *   - RbacDecisionCache   (RBAC grants)
 *   - requestScopedContainer cache
 *   - SemitexaContainer ExecutionContext
 *
 * The test deliberately runs in CLI mode (no Swoole coroutine) to expose
 * static-fallback leak paths. In production Swoole HTTP mode each request
 * runs in its own coroutine and the per-coroutine Coroutine::getContext()
 * auto-cleans, so leaks in static fallbacks are invisible there. CLI / queue
 * worker / test mode reuses the same Application instance for many requests,
 * so a missing per-request reset is observable as a sequential-request leak.
 *
 * The framework currently does NOT bootstrap a full TenancyPhase in the
 * default test container (no tenancy configuration loaded). So this test
 * exercises the *cleanup contract* directly: it writes tenant/locale state
 * via the public store APIs (the same APIs TenancyPhase / LocalePhase /
 * TenantAwareJobSerializer use in production) and asserts the framework
 * wipes them in the request lifecycle finally block.
 */
final class TenantIsolationRuntimeSmokeTest extends TestCase
{
    private Application $app;
    private TenantContextStoreInterface $tenantContextStore;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->tenantContextStore = TenantContextStore::shared();

        // Fully empty starting state — both registries cleared.
        $this->wipeAll();
    }

    protected function tearDown(): void
    {
        $this->wipeAll();
    }

    private function wipeAll(): void
    {
        TestStateResetRegistry::resetAllForTesting();
        AuthContextStore::clear();
        AuthContextStore::clearFallback();
        $this->tenantContextStore->clear();
        LocaleContextStore::clearFallback();
        CurrentRequestStore::clear();
        RbacDecisionCache::clear();
        $this->app->requestScopedContainer->reset();
    }

    // ------------------------------------------------------------------
    //  Tenant cleanup
    // ------------------------------------------------------------------

    #[Test]
    public function tenant_context_resets_after_2xx_request(): void
    {
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        self::assertNotNull($this->tenantContextStore->tryGet(), 'precondition: tenant set');

        $this->app->handleRequest($this->makeRequest('/playground'));

        self::assertNull($this->tenantContextStore->tryGet(), 'TenantContext leaked after 2xx');
    }

    #[Test]
    public function tenant_context_resets_after_401_authentication_failure(): void
    {
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));

        $response = $this->app->handleRequest($this->makeRequest('/auth-demo/runtime/protected'));

        self::assertSame(401, $response->getStatusCode(), 'precondition: protected route returns 401');
        self::assertNull($this->tenantContextStore->tryGet(), 'TenantContext leaked after 401');
    }

    #[Test]
    public function tenant_context_resets_after_403_authorization_failure(): void
    {
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));

        $response = $this->app->handleRequest($this->makeRequest(
            '/auth-demo/runtime/protected-with-permission',
            [AuthDemoStubAuthHandler::HEADER => AuthDemoStubAuthHandler::USER_PREFIX . 'no-perms-user'],
        ));

        self::assertSame(403, $response->getStatusCode(), 'precondition: missing permission returns 403');
        self::assertNull($this->tenantContextStore->tryGet(), 'TenantContext leaked after 403');
    }

    #[Test]
    public function tenant_context_resets_after_5xx_response(): void
    {
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));

        $response = $this->app->handleRequest($this->makeRequest('/__semitexa/error/500'));

        self::assertSame(500, $response->getStatusCode());
        self::assertNull($this->tenantContextStore->tryGet(), 'TenantContext leaked after 5xx');
    }

    #[Test]
    public function tenant_a_does_not_leak_into_a_later_tenant_b_request(): void
    {
        // Request A under tenant A.
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        $this->app->handleRequest($this->makeRequest(
            '/playground',
            ['Host' => 'tenant-a.example.test', 'X-Smoke-Request-Id' => 'req-a'],
        ));
        self::assertNull($this->tenantContextStore->tryGet(), 'tenant A leaked after its own request');

        // Request B under tenant B — fresh setup.
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-b', 'host'));
        self::assertSame('tenant-b', $this->tenantContextStore->getOrFail()->getTenantId(), 'tenant B set correctly');
        $this->app->handleRequest($this->makeRequest(
            '/playground',
            ['Host' => 'tenant-b.example.test', 'X-Smoke-Request-Id' => 'req-b'],
        ));

        self::assertNull($this->tenantContextStore->tryGet(), 'tenant B leaked after its own request');
    }

    #[Test]
    public function tenant_b_does_not_leak_into_a_later_tenant_a_request(): void
    {
        // Reverse-order regression for the symmetric leak class.
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-b', 'host'));
        $this->app->handleRequest($this->makeRequest('/playground', ['Host' => 'tenant-b.example.test']));

        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        $this->app->handleRequest($this->makeRequest('/playground', ['Host' => 'tenant-a.example.test']));

        self::assertNull($this->tenantContextStore->tryGet());
    }

    // ------------------------------------------------------------------
    //  Locale cleanup
    // ------------------------------------------------------------------

    #[Test]
    public function locale_resets_after_request(): void
    {
        LocaleContextStore::setLocale('uk');
        self::assertSame('uk', LocaleContextStore::getLocale(), 'precondition: locale set');

        $this->app->handleRequest($this->makeRequest('/playground'));

        // After cleanup, the static fallback is back to its default ('en').
        self::assertSame('en', LocaleContextStore::getLocale(), 'LocaleContextStore static fallback leaked');
    }

    #[Test]
    public function locale_does_not_leak_between_sequential_requests(): void
    {
        LocaleContextStore::setLocale('uk');
        $this->app->handleRequest($this->makeRequest('/playground', ['Accept-Language' => 'uk']));

        // Now dispatch a request without Accept-Language. The framework
        // would not see uk anywhere. The store must already be back to
        // default before this dispatch starts.
        self::assertSame('en', LocaleContextStore::getLocale(), 'uk leaked into the next request');

        LocaleContextStore::setLocale('de');
        $this->app->handleRequest($this->makeRequest('/playground', ['Accept-Language' => 'de']));

        self::assertSame('en', LocaleContextStore::getLocale(), 'de leaked after its own request');
    }

    #[Test]
    public function locale_resets_after_401_403_5xx(): void
    {
        // 401
        LocaleContextStore::setLocale('uk');
        $this->app->handleRequest($this->makeRequest('/auth-demo/runtime/protected'));
        self::assertSame('en', LocaleContextStore::getLocale(), 'locale leaked after 401');

        // 403
        LocaleContextStore::setLocale('de');
        $this->app->handleRequest($this->makeRequest(
            '/auth-demo/runtime/protected-with-permission',
            [AuthDemoStubAuthHandler::HEADER => AuthDemoStubAuthHandler::USER_PREFIX . 'no-perms-user'],
        ));
        self::assertSame('en', LocaleContextStore::getLocale(), 'locale leaked after 403');

        // 500
        LocaleContextStore::setLocale('fr');
        $this->app->handleRequest($this->makeRequest('/__semitexa/error/500'));
        self::assertSame('en', LocaleContextStore::getLocale(), 'locale leaked after 500');
    }

    // ------------------------------------------------------------------
    //  Cross-context isolation — combinations not covered by per-store tests
    // ------------------------------------------------------------------

    #[Test]
    public function user_from_tenant_a_does_not_leak_into_anonymous_request_for_tenant_b(): void
    {
        // Tenant A + authenticated user.
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        $this->app->handleRequest($this->makeRequest(
            '/playground',
            [AuthDemoStubAuthHandler::HEADER => AuthDemoStubAuthHandler::USER_PREFIX . 'tenant-a-user'],
        ));
        self::assertNull(AuthContextStore::getUser(), 'tenant-A user leaked after its request');
        self::assertNull($this->tenantContextStore->tryGet(), 'tenant-A leaked after its request');

        // Tenant B + anonymous → protected route → must reject as guest.
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-b', 'host'));
        $response = $this->app->handleRequest($this->makeRequest(
            '/auth-demo/runtime/protected',
            ['Host' => 'tenant-b.example.test'],
        ));

        self::assertSame(401, $response->getStatusCode(), 'tenant-B anonymous request must be rejected as guest, not auth-A as user');
    }

    #[Test]
    public function service_principal_from_tenant_a_does_not_leak_into_user_route_for_tenant_b(): void
    {
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        $this->app->handleRequest($this->makeRequest(
            '/playground',
            [AuthDemoStubAuthHandler::HEADER => AuthDemoStubAuthHandler::SERVICE_PREFIX . 'tenant-a-service'],
        ));

        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-b', 'host'));
        $response = $this->app->handleRequest($this->makeRequest(
            '/auth-demo/runtime/protected',
            ['Host' => 'tenant-b.example.test'],
        ));

        self::assertSame(401, $response->getStatusCode(), 'service principal from tenant A must not satisfy a user-protected route in tenant B');
    }

    #[Test]
    public function request_scoped_container_resets_between_tenant_requests(): void
    {
        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        $this->app->handleRequest($this->makeRequest(
            '/playground',
            ['X-Smoke-Request-Id' => 'req-a'],
        ));
        self::assertFalse(
            $this->app->requestScopedContainer->has(Request::class),
            'tenant-A Request leaked into requestScopedContainer',
        );

        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-b', 'host'));
        $this->app->handleRequest($this->makeRequest(
            '/playground',
            ['X-Smoke-Request-Id' => 'req-b'],
        ));
        self::assertFalse(
            $this->app->requestScopedContainer->has(Request::class),
            'tenant-B Request leaked into requestScopedContainer',
        );
    }

    #[Test]
    public function current_request_store_does_not_carry_request_a_into_request_b(): void
    {
        $this->app->handleRequest($this->makeRequest('/playground', ['X-Smoke-Request-Id' => 'req-a']));
        self::assertNull(CurrentRequestStore::get(), 'CurrentRequestStore leaked req-a after dispatch');

        $this->app->handleRequest($this->makeRequest('/playground', ['X-Smoke-Request-Id' => 'req-b']));
        self::assertNull(CurrentRequestStore::get(), 'CurrentRequestStore leaked req-b after dispatch');
    }

    // ------------------------------------------------------------------
    //  RBAC tenant-awareness — proven OR honestly reported as not modeled
    // ------------------------------------------------------------------

    #[Test]
    public function rbac_decision_cache_resets_between_tenant_requests(): void
    {
        // Pin that tenant A's grants for user-X don't carry into tenant B's
        // request for user-X — by virtue of the cache being wiped between
        // requests. (RBAC is NOT tenant-aware in its cache key today; it
        // relies on the per-request reset to be safe under multi-tenant
        // traffic.)

        $this->tenantContextStore->set(TenantContext::fromResolution('tenant-a', 'host'));
        RbacDecisionCache::set('user-X', new SubjectGrantSet(
            new CapabilityGrantSet([]),
            new PermissionGrantSet(['perm.tenant-a.only']),
        ));
        self::assertNotNull(RbacDecisionCache::get('user-X'), 'precondition: tenant-A grants set for user-X');

        $this->app->handleRequest($this->makeRequest('/playground'));

        // After the request, RBAC cache is empty — so the next tenant's
        // resolution starts fresh. There is NO cache key collision risk
        // *because* the cache lifetime is per-request.
        self::assertNull(RbacDecisionCache::get('user-X'), 'RbacDecisionCache leaked user-X grants');
    }

    // ------------------------------------------------------------------
    //  Direct CoroutineLocal isolation
    // ------------------------------------------------------------------

    #[Test]
    public function arbitrary_request_header_state_does_not_leak_via_coroutine_local(): void
    {
        // CurrentRequestStore goes through CoroutineLocal internally;
        // CoroutineLocal::endRequest clears the CLI fallback. Pin that.
        CurrentRequestStore::set($this->makeRequest('/playground', ['X-Probe' => 'leakable']));
        self::assertNotNull(CurrentRequestStore::get(), 'precondition: CoroutineLocal-backed state set');

        $this->app->handleRequest($this->makeRequest('/playground'));

        self::assertNull(CurrentRequestStore::get());
    }

    // ------------------------------------------------------------------
    //  setFallback (deliberate persistence) is a different contract
    // ------------------------------------------------------------------

    #[Test]
    public function tenant_context_setFallback_is_an_explicit_long_lived_path(): void
    {
        // The TenantAwareJobSerializer calls setFallback to restore tenant
        // identity for queued jobs. set() and setFallback() write the same
        // CLI field today — so framework cleanup cannot distinguish them.
        // The store CLEARS both — deferring to the per-request lifecycle is
        // safer than retaining a tenant identity past the request that
        // established it. Queue/CLI tooling that wants a stable tenant must
        // call setFallback again before each unit of work, which
        // TenantAwareJobSerializer already does. Pin that contract.

        $this->tenantContextStore->setFallback(TenantContext::fromResolution('tenant-restored', 'job'));
        self::assertSame('tenant-restored', $this->tenantContextStore->getOrFail()->getTenantId());

        $this->app->handleRequest($this->makeRequest('/playground'));

        self::assertNull(
            $this->tenantContextStore->tryGet(),
            'setFallback() in CLI is per-execution, not per-process',
        );
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function makeRequest(string $path, array $extraHeaders = []): Request
    {
        $headers = ['Accept' => 'application/json'] + $extraHeaders;
        return new Request(
            method: 'GET',
            uri: $path,
            headers: $headers,
            query: [],
            post: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path],
            cookies: [],
            content: null,
        );
    }
}
