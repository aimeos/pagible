<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;


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
        Page::withTrashed()->select( Page::SELECT_COLUMNS )->with( [
                'elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
                'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux'] ),
                'latest.elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
            ] )
            ->chunk( 50, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
        Element::withTrashed()->with( ['latest' => fn( $q ) => $q->select( Version::SELECT_COLUMNS )] )
            ->chunk( 50, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
        File::withTrashed()->with( ['latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux'] )] )
            ->chunk( 50, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
    }
}
