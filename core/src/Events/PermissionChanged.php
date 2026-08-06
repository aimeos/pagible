<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for CMS permission assignment changes.
 *
 * Dispatched by Permission::set(), so every write path — GraphQL, artisan,
 * queued jobs — is covered, not just one transport.
 */
final class PermissionChanged
{
    use Dispatchable;

    /**
     * @param array<int, string> $assignments Resulting raw assignments
     */
    public function __construct(
        public readonly string $actorEmail,
        public readonly string $targetEmail,
        public readonly string $targetId,
        public readonly array $assignments = [],
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly string $tenant = '',
    ) {}
}
