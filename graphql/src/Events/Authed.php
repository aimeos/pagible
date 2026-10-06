<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for authentication and user-settings actions in the GraphQL API.
 */
final class Authed implements Loggable
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly string $tenant = '',
    ) {}


    /**
     * Dispatches the event for the current request if something consumes it.
     */
    public static function fire( string $action, string $email ) : void
    {
        Watch::dispatch( self::class, fn() => new self(
            $action, $email, (string) request()->ip(), (string) request()->userAgent(), Tenancy::value()
        ) );
    }


    /**
     * Returns the log entry with PII hashed unless "cms.watch.anonymize" is disabled.
     *
     * @return array{message: string, fields: array<string, mixed>}
     */
    public function log() : array
    {
        return ['message' => 'cms.auth', 'fields' => [
            'action' => $this->action,
            'email' => Watch::mask( $this->email ),
            'ip' => Watch::mask( $this->ip ),
            'user_agent' => Watch::mask( $this->userAgent ),
            'tenant_id' => $this->tenant,
        ]];
    }
}
