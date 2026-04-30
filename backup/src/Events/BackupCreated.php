<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


class BackupCreated
{
    use Dispatchable;

    /**
     * @param string $tenant Tenant ID
     * @param string $path Backup file path on disk
     * @param array<string, int> $counts Record counts per table
     */
    public function __construct(
        public string $tenant,
        public string $path,
        public array $counts,
    ) {}
}
