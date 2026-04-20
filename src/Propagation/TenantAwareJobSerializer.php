<?php

declare(strict_types=1);

namespace Semitexa\Tenancy\Propagation;

use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Context\TenantContextStore;

/**
 * Wraps and unwraps tenant context in queue job payloads.
 *
 * Usage:
 *   // Before enqueueing (producer side):
 *   $payload = TenantAwareJobSerializer::wrap($originalPayload);
 *
 *   // After dequeueing (consumer side):
 *   [$payload, $tenantContext] = TenantAwareJobSerializer::unwrap($receivedPayload);
 *   if ($tenantContext !== null) {
 *       TenantContextStore::shared()->setFallback($tenantContext);
 *   }
 *
 * Per decision A4: default tenant context is NOT propagated.
 */
final class TenantAwareJobSerializer
{
    private const TENANT_KEY = '_tenant';

    /**
     * Wrap a job payload array with the current tenant context.
     * If tenant is default, no tenant info is added (per A4).
     *
     * @param  array<string, mixed> $payload The original job payload
     * @return array<string, mixed>          Payload with tenant context injected (if applicable)
     */
    public static function wrap(array $payload): array
    {
        $context = TenantContextStore::shared()->tryGet();

        if (!$context instanceof TenantContext) {
            return $payload;
        }

        $serialized = $context->forSerialization();

        if ($serialized === null) {
            return $payload;
        }

        $payload[self::TENANT_KEY] = $serialized;

        return $payload;
    }

    /**
     * Unwrap tenant context from a received job payload.
     *
     * @param  array<string, mixed> $payload The received job payload
     * @return array{0: array<string, mixed>, 1: TenantContext|null} [cleaned payload, tenant context or null]
     */
    public static function unwrap(array $payload): array
    {
        if (!isset($payload[self::TENANT_KEY]) || !is_array($payload[self::TENANT_KEY])) {
            return [$payload, null];
        }

        $tenantData = $payload[self::TENANT_KEY];
        unset($payload[self::TENANT_KEY]);

        $context = TenantContext::fromQueuePayload($tenantData);

        return [$payload, $context];
    }

    /**
     * Convenience: unwrap and immediately restore tenant context in CLI/queue worker.
     *
     * @param  array<string, mixed> $payload The received job payload
     * @return array<string, mixed>          The cleaned payload (without tenant key)
     */
    public static function unwrapAndRestore(array $payload): array
    {
        [$cleaned, $context] = self::unwrap($payload);

        if ($context !== null) {
            TenantContextStore::shared()->setFallback($context);
        }

        return $cleaned;
    }
}
