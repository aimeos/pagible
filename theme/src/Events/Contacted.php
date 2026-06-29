<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Utils;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit/metrics event for contact-form submissions.
 *
 * Plain event dispatched through the normal event pipeline so log and metrics listeners run
 * without websocket broadcasting. PII (email, IP) is anonymized by the listeners unless
 * "cms.watch.anonymize" is disabled. Fired only when "cms.theme.watch" is enabled.
 */
final class Contacted
{
    use Dispatchable, SerializesModels;

    /**
     * Correlation ID shared by all events of the same request (empty when not available).
     */
    public readonly string $requestId;


    /**
     * @param string $email Sender email address
     * @param string $ip Client IP address
     * @param float $durationMs Handling duration in milliseconds
     * @param string $tenant Tenant ID the submission belongs to
     * @param string|null $requestId Correlation ID; taken from the X-Request-Id header when null
     */
    public function __construct(
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
        ?string $requestId = null,
    ) {
        $this->requestId = $requestId ?? Utils::requestId();
    }
}
