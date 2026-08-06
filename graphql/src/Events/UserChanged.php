<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for administrative user creation and authorization changes.
 */
final class UserChanged
{
    use Dispatchable;

    /**
     * @param array<int, string> $assignments Resulting direct assignments
     */
    public function __construct(
        public readonly string $action,
        public readonly string $actorEmail,
        public readonly string $targetEmail,
        public readonly string $targetId,
        public readonly array $assignments = [],
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly string $tenant = '',
    ) {}
}
