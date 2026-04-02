<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
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
        Page::withTrashed()->with( ['elements', 'latest.elements'] )
            ->chunk( 100, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
        Element::withTrashed()->with( 'latest' )
            ->chunk( 100, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
        File::withTrashed()->with( 'latest' )
            ->chunk( 100, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
    }
}
