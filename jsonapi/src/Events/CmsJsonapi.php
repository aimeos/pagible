<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for read-only JSON:API requests.
 */
final class CmsJsonapi implements Loggable
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly float $durationMs = 0.0,
        public readonly string $domain = '',
        public readonly string $includes = '',
        public readonly string $tenant = '',
    ) {}


    /**
     * Returns the log entry, sampled by "cms.watch.sample".
     *
     * @return array{message: string, fields: array<string, mixed>, sample: true}
     */
    public function log() : array
    {
        return ['message' => 'cms.jsonapi', 'sample' => true, 'fields' => [
            'action' => $this->action,
            'duration_ms' => round( $this->durationMs, 1 ),
            'domain' => $this->domain,
            'includes' => $this->includes,
            'tenant_id' => $this->tenant,
        ]];
    }
}
