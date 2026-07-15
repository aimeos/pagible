<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;


/**
 * Transport-neutral CMS metric event.
 */
final class Observed
{
    /**
     * @param array<string, bool|float|int|string|null> $dimensions Aggregation-safe metric dimensions
     */
    public function __construct(
        public readonly string $source,
        public readonly string $action,
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
        public readonly array $dimensions = [],
        public readonly bool $sample = false,
    ) {}
}
