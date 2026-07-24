<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;


class DeleteFilePaths implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;


    /**
     * @param string $disk Storage disk name
     * @param string $tenant Tenant ID owning the storage namespace
     * @param array<string> $paths Local paths to delete
     */
    public function __construct( public string $disk, public string $tenant, public array $paths )
    {
    }


    public function handle(): void
    {
        Utils::fileLock( $this->tenant, $this->delete(...) );
    }


    /**
     * Deletes valid paths that are still unreferenced while holding the tenant lock.
     */
    private function delete(): void
    {
        $paths = [];

        foreach( $this->paths as $path )
        {
            if( ( $path = Utils::normalizePath( $path, $this->tenant ) ) !== null ) {
                $paths[$path] = true;
            }
        }

        if( !$paths ) {
            return;
        }

        foreach( File::withoutTenancy()->withTrashed()->select( 'id', 'path', 'previews' )
            ->where( 'tenant_id', $this->tenant )->lazyById() as $file )
        {
            $this->forget( $paths, $file->path );

            foreach( (array) $file->previews as $path ) {
                $this->forget( $paths, $path );
            }

            if( !$paths ) {
                return;
            }
        }

        foreach( Version::withoutTenancy()->select( 'id', 'data' )
            ->where( 'tenant_id', $this->tenant )
            ->where( 'versionable_type', File::class )->lazyById() as $version )
        {
            $this->forget( $paths, $version->data->path ?? null );

            foreach( (array) ( $version->data->previews ?? [] ) as $path ) {
                $this->forget( $paths, $path );
            }

            if( !$paths ) {
                return;
            }
        }

        Storage::disk( $this->disk )->delete( array_keys( $paths ) );
    }


    /**
     * Keeps referenced paths out of the deletion set.
     *
     * @param array<string, bool> $paths Candidate deletion paths
     * @param mixed $path Referenced storage path
     */
    private function forget( array &$paths, mixed $path ): void
    {
        if( ( $path = Utils::normalizePath( $path, $this->tenant ) ) !== null ) {
            unset( $paths[$path] );
        }
    }
}
