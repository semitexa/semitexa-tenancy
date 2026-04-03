# Semitexa Tenancy

Tenant resolution, scoped context, and multi-tenant application support.

## Purpose

Resolves the current tenant per request through a configurable chain of strategies (path, subdomain, header, query param). Stores the resolved tenant in coroutine-safe context for downstream use by ORM, Cache, Storage, and other tenant-aware packages.

## Role in Semitexa

Depends on Core. Depended on by ORM, Cache, Search, Media, Scheduler, Workflow, and optionally Locale. Tenancy is foundational to the multi-tenant architecture — when active, ORM scopes queries, Cache namespaces keys, and Storage isolates paths, all transparently based on the resolved tenant.

## Key Features

- `TenancyBootstrapper` builds resolver chain from env config
- Resolution strategies: path, subdomain, header, query param
- `TenantContext` with coroutine-safe `CoroutineContextStore`
- `TenantResolverChain` composable resolver ordering
- Multi-layer tenancy via `LayerDefinition`
- `#[AsTenantLayerStrategy]` attribute for custom layers
- `TenantRepositoryInterface` for tenant lookup (config or database-backed)
