<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Watch;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for CMS permission assignment changes.
 *
 * Dispatched by Permission::set(), so every write path — GraphQL, artisan,
 * queued jobs — is covered, not just one transport.
 */
final class PermissionChanged implements Loggable
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
            'action' => 'permission',
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
