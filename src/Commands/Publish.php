<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\Version;


class Publish extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:publish';

    /**
     * Command description
     */
    protected $description = 'Publish scheduled versions of elements and pages';


    /**
     * Execute command
     */
    public function handle()
    {
        Version::where( 'publish_at', '<=', now() )
            ->where( 'published', false )
            ->chunk( 100, function( $versions ) {

                foreach( $versions as $version )
                {
                    try
                    {
                        $id = $version->versionable_id;
                        $type = $version->versionable_type;

                        app( $type )::findOrFail( $id )->publish( $version );
                    }
                    catch( \Exception $e )
                    {
                        $this->error( "Failed to publish ID {$id} of {$type}: " . $e->getMessage() );
                    }
                }

            } );
    }
}
