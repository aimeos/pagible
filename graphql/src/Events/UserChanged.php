<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Auth;


/**
 * Audit event for administrative user creation and authorization changes.
 */
final class UserChanged implements Loggable
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


    /**
     * Dispatches the event for the current actor and request if something consumes it.
     *
     * @param array<int, string> $assignments Resulting direct assignments
     */
    public static function fire( string $action, Authenticatable $target, array $assignments = [] ) : void
    {
        Watch::dispatch( self::class, fn() => new self(
            $action,
            (string) data_get( Auth::user(), 'email' ),
            (string) data_get( $target, 'email' ),
            (string) $target->getAuthIdentifier(),
            $assignments,
            (string) request()->ip(),
            (string) request()->userAgent(),
            Tenancy::value(),
        ) );
    }


    /**
     * Returns the audit entry, always logged as warning.
     *
     * The acting and target principals stay identifiable for forensic use even when
     * anonymization is on; only network metadata follows the anonymization setting.
     *
     * @return array{message: string, fields: array<string, mixed>, level: 'warning'}
     */
    public function log() : array
    {
        return ['message' => 'cms.user', 'level' => 'warning', 'fields' => [
            'action' => $this->action,
            'actor' => $this->actorEmail,
            'target' => $this->targetEmail,
            'target_id' => $this->targetId,
            'assignments' => $this->assignments,
            'ip' => Watch::mask( $this->ip ),
            'user_agent' => Watch::mask( $this->userAgent ),
            'tenant_id' => $this->tenant,
        ]];
    }
}
