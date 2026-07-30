<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Tenancy;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = false;


    /**
     * UUID path migration can't restore ambiguous legacy path ownership.
     */
    public function down(): void
    {
    }


    /**
     * Moves managed File paths into directories owned by their File UUID.
     */
    public function up(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $tenants = $db->table( 'cms_files' )->distinct()->pluck( 'tenant_id' )
            ->map( strval(...) )->sortBy( fn( string $tenant ) => $tenant === '' ? 1 : 0 );

        foreach( $tenants as $tenant )
        {
            Tenancy::check( $tenant );
            $this->copy( $db, $tenant );

            if( $this->move( $db, $tenant ) ) {
                $this->invalidate( $db, $tenant );
            }
        }
    }


    /**
     * Copies and verifies every target before any legacy source is removed.
     */
    private function copy( Connection $db, string $tenant ): void
    {
        $this->files( $db, $tenant, function( object $file, iterable $versions ) use ( $tenant ) {
            $storage = Storage::disk( File::diskName( (string) $file->disk ) );

            foreach( $this->paths( $file, $versions, $tenant ) as $source )
            {
                $target = $this->target( $tenant, (string) $file->id, $source );

                if( $source === $target || !$storage->exists( $source ) ) {
                    continue;
                }

                $size = $storage->size( $source );

                if( !$storage->copy( $source, $target ) ) {
                    throw new RuntimeException( sprintf( 'Unable to store file "%s"', $target ) );
                }

                if( !$storage->exists( $target ) || $storage->size( $target ) !== $size ) {
                    throw new RuntimeException( sprintf( 'Unable to verify file "%s"', $target ) );
                }
            }
        } );
    }


    /**
     * Streams Files with their versions in bounded chunks.
     *
     * @param Closure(object, iterable<object>):void $callback
     */
    private function files( Connection $db, string $tenant, Closure $callback,
        bool $write = false ): void
    {
        $db->table( 'cms_files' )->select( 'id', 'disk', 'path', 'previews' )
            ->where( 'tenant_id', $tenant )->chunkById( $write ? 50 : 250, function( $files ) use (
                $callback, $db, $tenant, $write
            ) {
                $versions = $db->table( 'cms_versions' );

                if( $write ) {
                    $versions->select(
                        'id', 'tenant_id', 'versionable_id', 'versionable_type', 'published',
                        'publish_at', 'lang', 'data', 'aux', 'editor', 'created_at',
                    );
                } else {
                    $versions->select( 'id', 'versionable_id', 'data' );
                }

                $versions = $versions
                    ->where( 'tenant_id', $tenant )
                    ->where( 'versionable_type', File::class )
                    ->whereIn( 'versionable_id', $files->pluck( 'id' ) )
                    ->get()->groupBy( 'versionable_id' );

                foreach( $files as $file ) {
                    $callback( $file, $versions->get( $file->id, [] ) );
                }
            }, 'id' );
    }


    /**
     * Invalidates published page routes that can contain migrated Files.
     */
    private function invalidate( Connection $db, string $tenant ): void
    {
        Tenancy::run( $tenant, function() use ( $db, $tenant ) {
            $direct = $db->table( 'cms_page_file as pf' )
                ->join( 'cms_files as f', 'f.id', '=', 'pf.file_id' )
                ->where( 'f.tenant_id', $tenant )->select( 'pf.page_id' );
            $shared = $db->table( 'cms_element_file as ef' )
                ->join( 'cms_files as f', 'f.id', '=', 'ef.file_id' )
                ->join( 'cms_page_element as pe', 'pe.element_id', '=', 'ef.element_id' )
                ->where( 'f.tenant_id', $tenant )->select( 'pe.page_id' );
            $pages = $direct->unionAll( $shared );

            foreach( $db->table( 'cms_pages' )->where( 'tenant_id', $tenant )
                ->whereNull( 'deleted_at' )->whereIn( 'id', $pages )
                ->select( 'id', 'domain', 'path' )->lazyById( 250 )->chunk( 250 ) as $items )
            {
                $paths = [];

                foreach( $items as $page ) {
                    $paths[(string) $page->domain][] = (string) $page->path;
                }

                foreach( $paths as $domain => $domainPaths ) {
                    PageInvalidated::dispatch( (string) $domain, $domainPaths );
                }
            }
        } );
    }


    /**
     * Decodes a JSON object into an associative array.
     *
     * @return array<string, mixed>
     */
    private function json( mixed $value ): array
    {
        if( is_string( $value ) ) {
            $value = json_decode( $value, true );
        } elseif( is_object( $value ) ) {
            $value = json_decode( (string) json_encode( $value ), true );
        }

        return is_array( $value ) ? $value : [];
    }


    /**
     * Canonicalizes a tenant-owned path from either the legacy or UUID-owned storage layout.
     *
     * @param string $tenant Tenant namespace, or an empty string for the default tenant
     * @param mixed $path Candidate local storage path
     * @return string|null Canonical local path, or null when it is unsafe or belongs to another tenant
     */
    private function legacyPath( string $tenant, mixed $path ): ?string
    {
        if( !is_string( $path ) || $path === '' || str_contains( $path, '..' )
            || str_contains( $path, '\\' ) || preg_match( '/\p{C}/u', $path ) !== 0 ) {
            return null;
        }

        $path = implode( '/', array_filter(
            explode( '/', $path ),
            static fn( string $part ) : bool => $part !== '' && $part !== '.',
        ) );
        $prefix = $tenant === '' ? 'cms/' : 'cms/' . $tenant . '/';

        if( !str_starts_with( $path, $prefix ) ) {
            return null;
        }

        $relative = substr( $path, strlen( $prefix ) );

        if( $relative === '' || ( $tenant === '' && str_contains( $relative, '/' )
            && !Str::isUuid( explode( '/', $relative, 2 )[0] ) ) ) {
            return null;
        }

        return $path;
    }


    /**
     * Returns unique, canonical managed paths for a File and all of its versions.
     *
     * @param object $file Current File row
     * @param iterable<object> $versions Historical File versions
     * @param string $tenant Tenant namespace
     * @return array<int, string>
     */
    private function paths( object $file, iterable $versions, string $tenant ): array
    {
        $paths = [(string) $file->path, ...array_values( $this->json( $file->previews ) )];

        foreach( $versions as $version )
        {
            $data = $this->json( $version->data );
            $paths[] = (string) ( $data['path'] ?? '' );
            array_push( $paths, ...array_values( (array) ( $data['previews'] ?? [] ) ) );
        }

        return array_values( array_unique( array_filter( array_map(
            fn( mixed $path ) => $this->legacyPath( $tenant, $path ),
            $paths,
        ) ) ) );
    }


    /**
     * Returns the deterministic UUID-owned target for a legacy path.
     */
    private function target( string $tenant, string $id, string $path ): string
    {
        if( File::owner( $tenant, $path ) === strtolower( $id ) ) {
            return $path;
        }

        $base = $tenant === '' ? 'cms' : 'cms/' . $tenant;
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $ext = substr( (string) preg_replace( '/[^a-z0-9]+/', '', $ext ), 0, 10 );
        $name = substr( hash( 'sha256', $path ), 0, 24 ) . ( $ext !== '' ? '.' . $ext : '' );

        return $base . '/' . strtolower( $id ) . '/' . $name;
    }


    /**
     * Returns the UUID-owned target for a valid current or historical path.
     *
     * Invalid or foreign paths are returned unchanged so the migration never claims them.
     *
     * @param string $tenant Tenant namespace
     * @param string $id File UUID
     * @param mixed $path Candidate current or historical path
     * @return mixed UUID-owned path for managed input, otherwise the original value
     */
    private function transform( string $tenant, string $id, mixed $path ): mixed
    {
        if( !( $normalized = $this->legacyPath( $tenant, $path ) ) ) {
            return $path;
        }

        return $this->target( $tenant, $id, $normalized );
    }


    /**
     * Removes verified legacy sources and rewrites current and historical paths.
     */
    private function move( Connection $db, string $tenant ): bool
    {
        $changed = false;

        $this->files( $db, $tenant, function( object $file, iterable $versions ) use ( &$changed, $db, $tenant ) {
            $storage = Storage::disk( File::diskName( (string) $file->disk ) );
            $id = (string) $file->id;

            foreach( $this->paths( $file, $versions, $tenant ) as $source )
            {
                if( File::owner( $tenant, $source ) !== null ) {
                    continue;
                }

                $target = $this->target( $tenant, $id, $source );
                $exists = $storage->exists( $source );

                if( $exists && !$storage->exists( $target ) ) {
                    throw new RuntimeException( sprintf( 'Unable to verify file "%s"', $target ) );
                }

                if( !$exists ) {
                    continue;
                }

                $storage->delete( $source );

                if( $storage->exists( $source ) ) {
                    throw new RuntimeException( sprintf( 'Unable to remove file "%s"', $source ) );
                }
            }

            $path = $this->transform( $tenant, $id, $file->path );
            $originalPreviews = $this->json( $file->previews );
            $previews = [];

            foreach( $originalPreviews as $width => $preview ) {
                $previews[$width] = $this->transform( $tenant, $id, $preview );
            }

            $updates = [];

            if( $path !== $file->path ) {
                $updates['path'] = $path;
            }
            if( $previews !== $originalPreviews ) {
                $updates['previews'] = json_encode( $previews, JSON_UNESCAPED_SLASHES );
            }

            $versionUpdates = [];

            foreach( $versions as $version )
            {
                $data = $this->json( $version->data );
                $updated = $data;

                if( array_key_exists( 'path', $updated ) ) {
                    $updated['path'] = $this->transform( $tenant, $id, $updated['path'] );
                }
                if( isset( $updated['previews'] ) ) {
                    foreach( (array) $updated['previews'] as $width => $preview ) {
                        $updated['previews'][$width] = $this->transform( $tenant, $id, $preview );
                    }
                }

                if( $updated !== $data ) {
                    $versionUpdates[] = [...(array) $version,
                        'data' => json_encode( $updated, JSON_UNESCAPED_SLASHES ),
                    ];
                }
            }

            if( !$updates && !$versionUpdates ) {
                return;
            }

            $db->transaction( function() use ( $db, $file, $updates, $versionUpdates ) {
                if( $updates ) {
                    $db->table( 'cms_files' )->where( 'id', $file->id )->update( $updates );
                }
                foreach( array_chunk( $versionUpdates, 100 ) as $chunk ) {
                    $db->table( 'cms_versions' )->upsert( $chunk, ['id'], ['data'] );
                }
            } );

            $changed = true;
        }, true );

        return $changed;
    }
};
