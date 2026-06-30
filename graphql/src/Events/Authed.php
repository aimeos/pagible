<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for authentication and user-settings actions in the GraphQL API.
 */
final class Authed
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly string $tenant = '',
    ) {}
}
