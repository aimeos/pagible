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
        Page::withTrashed()->select( Page::SELECT_COLUMNS )->with( [
                'elements' => fn( $q ) => $q->select( Element::SELECT_COLS ),
                'latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor', 'published' ),
                'latest.elements' => fn( $q ) => $q->select( Element::SELECT_COLS ),
            ] )
            ->chunk( 50, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
        Element::withTrashed()->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor', 'published' )] )
            ->chunk( 50, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
        File::withTrashed()->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor', 'published' )] )
            ->chunk( 50, fn( $items ) => $items->searchable() ); // @phpstan-ignore method.notFound
    }
}
