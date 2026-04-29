<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


class RestoreCompleted
{
    use Dispatchable;

    /**
     * @param string $tenant Tenant ID
     * @param string $file Backup file name
     * @param array<string, int> $counts Record counts per table
     */
    public function __construct(
        public string $tenant,
        public string $file,
        public array $counts,
    ) {}
}
