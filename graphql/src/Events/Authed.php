<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit event for authentication and user-settings actions in the GraphQL API.
 *
 * Plain event dispatched through the normal event pipeline so log and metrics listeners run
 * without websocket broadcasting. PII (email, IP, user agent) is anonymized by the listeners
 * unless "cms.watch.anonymize" is disabled.
 */
final class Authed
{
    use Dispatchable, SerializesModels;

    /**
     * @param string $action Action: 'login', 'logout', 'login-fail' or 'user-save'
     * @param string $email Email address the action was performed for
     * @param string $ip Client IP address
     * @param string $userAgent Client user agent string
     * @param string $tenant Tenant ID the action belongs to
     */
    public function __construct(
        public readonly string $action,
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly string $tenant = '',
    ) {}
}
