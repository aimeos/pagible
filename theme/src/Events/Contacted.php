<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit/metrics event for contact-form submissions.
 */
final class Contacted
{
    use Dispatchable;

    public function __construct(
        public readonly string $email = '',
        public readonly string $ip = '',
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
    ) {}
}
