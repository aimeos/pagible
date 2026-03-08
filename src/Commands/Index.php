<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\Page;


class Index extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:index';

    /**
     * Command description
     */
    protected $description = 'Updates the search index';


    /**
     * Execute command
     */
    public function handle(): void
    {
        Page::where( 'status', '>', 0 )->searchable(); // @phpstan-ignore method.notFound
    }
}
