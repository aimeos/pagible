<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


class RestoreFailed
{
    use Dispatchable;

    /**
     * @param string $tenant Tenant ID
     * @param string $error Error message
     */
    public function __construct(
        public string $tenant,
        public string $error,
    ) {}
}
