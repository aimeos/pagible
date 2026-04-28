<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
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
    public function handle(): void
    {
        $conn = DB::connection( config( 'cms.db', 'sqlite' ) );

        Version::where( 'publish_at', '<=', now() )
            ->where( 'published', false )
            ->with( [
                'files' => fn( $q ) => $q->select( 'cms_files.id' ),
                'elements' => fn( $q ) => $q->select( 'cms_elements.id' ),
            ] )
            ->chunk( 50, function( $versions ) use ( $conn ) {

                $models = $this->models( $versions );

                foreach( $versions as $version )
                {
                    $id = (string) $version->versionable_id;
                    $type = (string) $version->versionable_type;

                    if( !isset( $models[$id] ) ) {
                        $this->error( "Model not found: {$id} of {$type}" );
                        continue;
                    }

                    try {
                        $conn->transaction( fn() => $models[$id]->publish( $version ) );
                    } catch( \Exception $e ) {
                        $this->error( "Failed to publish ID {$id} of {$type}: " . $e->getMessage() );
                    }
                }
            } );
    }


    /**
     * Batch-load models by type from a collection of versions.
     *
     * @param Collection<int, Version> $versions
     * @return Collection<string, \Aimeos\Cms\Models\Base>
     */
    protected function models( Collection $versions ) : Collection
    {
        $all = collect();

        foreach( $versions->groupBy( 'versionable_type' ) as $type => $typeVersions )
        {
            $ids = $typeVersions->pluck( 'versionable_id' )->all();

            $cols = match( (string) $type ) {
                Page::class => Page::SELECT_COLUMNS,
                File::class => [
                    'id', 'tenant_id', 'path', 'previews', 'latest_id',
                    'created_at', 'updated_at', 'deleted_at'
                ],
                Element::class => [
                    'id', 'tenant_id', 'latest_id',
                    'created_at', 'updated_at', 'deleted_at'
                ],
                default => [],
            };

            $all = $all->merge(
                app( (string) $type )::select( $cols )->whereIn( 'id', $ids )->get()->keyBy( 'id' )
            );
        }

        return $all;
    }
}
