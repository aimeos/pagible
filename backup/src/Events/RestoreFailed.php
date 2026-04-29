<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
