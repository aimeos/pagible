<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\Tenants;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Aimeos\Cms\DB;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;


class Publish extends Command
{
    use Tenants;

    /**
     * Command name
     */
    protected $signature = 'cms:publish
        {--tenant= : Only publish the versions of this tenant, use --tenant= for the default (empty) tenant}';

    /**
     * Command description
     */
    protected $description = 'Publish scheduled CMS versions';

    /**
     * Number of scheduled versions loaded at once
     */
    private const BATCH = 100;


    /**
     * Execute command
     */
    public function handle(): int
    {
        $at = now();
        $tables = ['cms_pages', 'cms_elements', 'cms_files'];

        return $this->eachTenant( $tables, fn() => $this->tenant( $at ) ) ? self::SUCCESS : self::FAILURE;
    }


    /**
     * Publishes the scheduled versions of the current tenant.
     *
     * @return bool TRUE if all due versions were published, FALSE if at least one failed
     */
    protected function tenant( \DateTimeInterface $at ) : bool
    {
        $last = null;
        $failed = [];

        do
        {
            $query = Version::select( 'id', 'versionable_id', 'versionable_type', 'publish_at', 'created_at' )->due( $at );

            if( $last ) {
                $query->older( $last );
            }

            $candidates = $query->orderByDesc( 'publish_at' )
                ->orderByDesc( 'created_at' )
                ->orderByDesc( 'id' )
                ->limit( self::BATCH )
                ->get();

            if( !$candidates->isEmpty() ) {
                $this->publish( $this->versions( $candidates, $at, $failed ), $at, $failed );
            }
        }
        // a smaller batch is the last one, publishing never adds older due versions
        while( $candidates->count() === self::BATCH && $last = $candidates->last() );

        return $failed === [];
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

        File::locked( function() use ( $at, $changed, &$failed, $versions ) {
            Scout::mute( Version::TYPES, function() use ( $at, $changed, &$failed, $versions ) {

                foreach( $versions as $version )
                {
                    if( $publication = $this->publishVersion( $version, $at, $failed ) ) {
                        $changed->merge( $publication );
                    }
                }
            } );
        } );

        $changed->flush();
    }


    /**
     * Publishes one scheduled version in an isolated transaction.
     *
     * @param array<string, bool> $failed
     */
    protected function publishVersion( Version $version, \DateTimeInterface $at, array &$failed ) : ?Publication
    {
        $model = $version->owner();
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
            // no retries: apply() marks the version as published in memory
            DB::connection()->transaction( function() use ( $at, $id, $model, $publication, $type, $version ) {
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

            if( !isset( Version::OWNERS[$type] ) ) {
                throw new \InvalidArgumentException( 'Invalid scheduled CMS model: ' . $type );
            }

            $key = self::key( $candidate );

            if( !isset( $ids[$key] ) && !isset( $failed[$key] ) ) {
                $ids[$key] = $candidate->id;
            }
        }

        return Version::loadOwners( Version::due( $at )->whereIn( 'id', array_values( $ids ) )->get() );
    }
}
