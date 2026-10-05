<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Watch;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit/metrics event for contact-form submissions.
 */
final class CmsContact implements Loggable
{
    use Dispatchable;

    public function __construct(
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
    ) {}


    /**
     * Returns the log entry with PII hashed unless "cms.watch.anonymize" is disabled.
     *
     * @return array{message: string, fields: array<string, mixed>}
     */
    public function log() : array
    {
        return ['message' => 'cms.contact', 'fields' => [
            'email' => Watch::mask( $this->email ),
            'ip' => Watch::mask( $this->ip ),
            'duration_ms' => round( $this->durationMs, 1 ),
            'tenant_id' => $this->tenant,
        ]];
    }
}
