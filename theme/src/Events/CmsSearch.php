<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit/metrics event for frontend searches.
 */
final class CmsSearch implements Loggable
{
    use Dispatchable;

    public function __construct(
        public readonly string $query,
        public readonly int $results,
        public readonly int $page,
        public readonly float $durationMs = 0.0,
        public readonly string $domain = '',
        public readonly string $lang = '',
        public readonly string $tenant = '',
    ) {}


    /**
     * Returns the log entry, sampled by "cms.watch.sample".
     *
     * @return array{message: string, fields: array<string, mixed>, sample: true}
     */
    public function log() : array
    {
        return ['message' => 'cms.search', 'sample' => true, 'fields' => [
            'query' => $this->query,
            'results' => $this->results,
            'page' => $this->page,
            'duration_ms' => round( $this->durationMs, 1 ),
            'domain' => $this->domain,
            'lang' => $this->lang,
            'tenant_id' => $this->tenant,
        ]];
    }
}
