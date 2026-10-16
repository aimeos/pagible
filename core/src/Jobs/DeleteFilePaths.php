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
        $files = File::withoutTenancy()->withTrashed()->select( 'id', 'path', 'previews' )
            ->where( 'tenant_id', $this->tenant )
            ->where( 'id', $owner )->get();

        foreach( $files as $file )
        {
            $this->forget( $paths, $file->path );

            foreach( (array) $file->previews as $path ) {
                $this->forget( $paths, $path );
            }
        }

        if( !$paths ) {
            return;
        }

        foreach( Version::withoutTenancy()->select( 'id', 'data' )
            ->where( 'tenant_id', $this->tenant )
            ->where( 'versionable_type', File::class )
            ->whereIn( 'versionable_id', $files->modelKeys() )
            ->lazyById() as $version )
        {
            $this->forget( $paths, $version->data->path ?? null );

            foreach( (array) ( $version->data->previews ?? [] ) as $path ) {
                $this->forget( $paths, $path );
            }

            if( !$paths ) {
                return;
            }
        }

        foreach( ['public', 'private'] as $disk ) {
            FileStorage::disk( $disk )->delete( array_keys( $paths ) );
        }

        FilesRemoved::dispatch( $this->tenant, array_keys( $paths ) );
    }


    /**
     * Keeps referenced paths out of the deletion set.
     *
     * @param array<string, bool> $paths Candidate deletion paths
     * @param mixed $path Referenced storage path
     */
    private function forget( array &$paths, mixed $path ): void
    {
        if( ( $path = FileStorage::normalizePath( $path, $this->tenant ) ) !== null ) {
            unset( $paths[$path] );
        }
    }
}
