<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit/metrics event for contact-form submissions.
 *
 * Plain event dispatched through the normal event pipeline so log and metrics listeners run
 * without websocket broadcasting. PII (email, IP) is anonymized by the listeners unless
 * "cms.watch.anonymize" is disabled. Fired only when "cms.watch.channel" and "cms.theme.watch" are enabled.
 */
final class Contacted
{
    use Dispatchable, SerializesModels;

    /**
     * @param string $email Sender email address
     * @param string $ip Client IP address
     * @param float $durationMs Handling duration in milliseconds
     * @param string $tenant Tenant ID the submission belongs to
     */
    public function __construct(
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
    ) {}
}
