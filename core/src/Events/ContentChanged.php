<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Lightweight content activity event for metrics-only consumers.
 */
final class ContentChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $contentType,
        public readonly string $action,
        public readonly string $source = '',
        public readonly string $tenant = '',
        public readonly ?string $domain = null,
        public readonly ?string $mime = null,
        public readonly ?int $value = null,
    ) {}
}
