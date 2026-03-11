# Changelog

## v1.0.5

### Added
- `TenantIdSanitizer` — centralized tenant ID sanitization (M1)
- `EnvReader` — unified environment variable reading utility (M2)
- `LayerStrategyAdapter` — generic adapter replacing boilerplate layer strategy wrappers (H1)
- `TenancyConfiguration` readonly DTO with `fromEnv()` factory (C1)
- `TenantStrategyFactory` and `TenantRepositoryFactory` for cleaner bootstrapping (C1)
- `CoroutineContextStore::swapFallback()` for atomic CLI context switching (C2)
- `TenantSwitched` event dispatched from `TenantRunCommand` (A4)
- Configurable defaults via `TENANCY_DEFAULT_LOCALE` and `TENANCY_DEFAULT_ENVIRONMENT` env vars (H4)
- `PathStrategy::allowOnly()` and `PathStrategy::excludeOnly()` named constructors (U2)
- `TenantErrorResponderInterface` + `DefaultTenantErrorResponder` for customizable error responses (H2)
- `ContextStoreInterface` — contract for context store implementations (A3)
- PHPStan configuration at level 6 (S1)

### Changed
- `HeaderStrategy`, `SubdomainStrategy`, `PathStrategy`, `QueryParamStrategy` now use `TenantIdSanitizer` instead of inline regex
- `TenancyBootstrapper` and `TenancyLayersProvider` now use `EnvReader` instead of manual env reading
- `TenantResolverHandler` delegates error responses to `TenantErrorResponderInterface`
- `TenantRunCommand` uses `swapFallback()` and dispatches `TenantSwitched` events
- `CoroutineContextStore` implements `ContextStoreInterface`
