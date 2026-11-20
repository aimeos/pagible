<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\FileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class DeleteFilePaths implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use RoutesQueue;


    /**
     * @param string $tenant Tenant ID owning the storage namespace
     * @param array<string> $paths Local paths to delete
     */
    public function __construct( public string $tenant, public array $paths )
    {
        $this->routeQueue();
    }


    public function handle(): void
    {
        FileStorage::storageLock( $this->tenant, function() {
            $owners = [];

            foreach( $this->paths as $path )
            {
                if( ( $path = FileStorage::normalizePath( $path, $this->tenant ) ) !== null
                    && ( $owner = FileStorage::owner( $this->tenant, $path ) ) !== null ) {
                    $owners[$owner][$path] = true;
                }
            }

            foreach( $owners as $owner => $paths ) {
                $this->delete( (string) $owner, $paths );
            }
        } );
    }


    /**
     * Deletes valid paths that are still unreferenced while holding the storage lock.
     *
     * @param array<string, bool> $paths Candidate deletion paths
     */
    private function delete( string $owner, array $paths ): void
    {
        $file = File::withoutTenancy()->withTrashed()->select( 'id', 'path', 'previews' )
            ->where( 'tenant_id', $this->tenant )
            ->where( 'id', $owner )->first();

        // versions are purged before their file, so a missing file has no versions
        $versions = $file ? Version::withoutTenancy()->select( 'id', 'data' )
            ->where( 'tenant_id', $this->tenant )
            ->where( 'versionable_type', File::class )
            ->where( 'versionable_id', $file->id )
            ->lazyById() : [];

        if( $this->forget( $paths, $file ) ) {
            return;
        }

        foreach( $versions as $version )
        {
            if( $this->forget( $paths, $version->data ?? null ) ) {
                return;
            }
        }

        foreach( ['public', 'private'] as $disk ) {
            FileStorage::disk( $disk )->delete( array_keys( $paths ) );
        }

        FilesRemoved::dispatch( $this->tenant, array_keys( $paths ) );
    }


    /**
     * Keeps the referenced file and preview paths out of the deletion set.
     *
     * @param array<string, bool> $paths Candidate deletion paths
     * @param object|null $item File or version data referencing the paths
     * @return bool TRUE if no candidate paths are left
     */
    private function forget( array &$paths, ?object $item ): bool
    {
        foreach( [$item->path ?? null, ...array_values( (array) ( $item->previews ?? [] ) )] as $path )
        {
            if( ( $path = FileStorage::normalizePath( $path, $this->tenant ) ) !== null ) {
                unset( $paths[$path] );
            }
        }

        return !$paths;
    }
}
