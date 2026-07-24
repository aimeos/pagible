<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Models\Base;
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
    protected $description = 'Publish scheduled CMS versions';


    /**
     * Execute command
     */
    public function handle(): void
    {
        $last = null;
        $failed = [];
        $at = now();

        do
        {
            $query = Version::select( 'id', 'versionable_id', 'versionable_type', 'publish_at', 'created_at' )->due( $at );

            if( $last ) {
                $query->older( $last );
            }

            $candidates = $query->orderByDesc( 'publish_at' )
                ->orderByDesc( 'created_at' )
                ->orderByDesc( 'id' )
                ->limit( 100 )
                ->get();

            if( !$candidates->isEmpty() ) {
                $this->publish( $this->versions( $candidates, $at, $failed ), $at, $failed );
            }
        }
        while( $last = $candidates->last() );
    }


    /**
     * Returns the owner key for the version.
     */
    protected static function key( Version $version ) : string
    {
        return $version->versionable_type . '|' . $version->versionable_id;
    }


    /**
     * Publishes a prepared batch while isolating failures per owner.
     *
     * @param Collection<int, Version> $versions
     * @param array<string, bool> $failed
     */
    protected function publish( Collection $versions, \DateTimeInterface $at, array &$failed ) : void
    {
        $changed = new Publication();
        $conn = DB::connection( config( 'cms.db', 'sqlite' ) );

        Scout::mute( Version::TYPES, function() use ( $at, $changed, $conn, &$failed, $versions ) {

            foreach( $versions as $version )
            {
                if( $publication = $this->publishVersion( $conn, $version, $at, $failed ) ) {
                    $changed->merge( $publication );
                }
            }
        } );

        $changed->flush();
    }


    /**
     * Publishes one scheduled version in an isolated transaction.
     *
     * @param array<string, bool> $failed
     */
    protected function publishVersion( Connection $conn, Version $version, \DateTimeInterface $at, array &$failed ) : ?Publication
    {
        $model = $version->versionable;
        $id = $version->versionable_id;
        $type = (string) $version->versionable_type;
        $key = self::key( $version );

        if( !$model instanceof Base )
        {
            $this->error( "Model not found: {$id} of {$type}" );
            $failed[$key] = true;
            return null;
        }

        $publication = new Publication();

        try
        {
            $conn->transaction( function() use ( $at, $id, $model, $publication, $type, $version ) {
                $publication->prepare( collect( [$version] ) );
                $publication->apply( $model, $version );

                Version::due( $at )->older( $version )
                    ->where( 'versionable_id', $id )
                    ->where( 'versionable_type', $type )
                    ->update( ['published' => true] );
            } );

            return $publication;
        }
        catch( \Exception $e )
        {
            $this->error( "Failed to publish ID {$id} of {$type}: " . $e->getMessage() );
            $failed[$key] = true;
            return null;
        }
    }


    /**
     * Loads the newest due version and owner for every candidate model.
     *
     * @param Collection<int, Version> $candidates
     * @param array<string, bool> $failed
     * @return Collection<int, Version>
     */
    protected function versions( Collection $candidates, \DateTimeInterface $at, array $failed ) : Collection
    {
        $ids = [];

        foreach( $candidates as $candidate )
        {
            $type = (string) $candidate->versionable_type;

            if( !in_array( $type, Version::TYPES, true ) ) {
                throw new \InvalidArgumentException( 'Invalid scheduled CMS model: ' . $type );
            }

            $key = self::key( $candidate );

            if( !isset( $ids[$key] ) && !isset( $failed[$key] ) ) {
                $ids[$key] = $candidate->id;
            }
        }

        $versions = Version::due( $at )->whereIn( 'id', array_values( $ids ) )->get();
        $versions->load( 'versionable' );

        return $versions;
    }
}
