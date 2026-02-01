<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
    protected $description = 'Updates the page index';


    /**
     * Execute command
     */
    public function handle()
    {
        Page::where( 'status', '>', 0 )->chunk( 100, function( $pages ) {

            foreach( $pages as $page )
            {
                try {
                    DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( fn() => $page->index() );
                } catch( \Exception $e ) {
                    $this->error( "Failed to index page ID {$page->id}: " . $e->getMessage() );
                }
            }

        } );
    }
}
