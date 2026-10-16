<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * Helpers for managed files, their storage paths, disks and locks.
 */
class FileStorage
{
    /**
     * Returns the storage path prefix of managed files for the tenant.
     *
     * @param string $tenant Tenant ID
     * @return string Path prefix with trailing slash, e.g. "cms/" or "cms/{tenant}/"
     */
    public static function prefix( string $tenant ) : string
    {
        return $tenant === '' ? 'cms/' : 'cms/' . $tenant . '/';
    }


    /**
     * Canonicalizes a UUID-owned local storage path and verifies its tenant namespace.
     *
     * The first path segment below the tenant prefix must be a File UUID and must be followed by a non-empty relative
     * path. Invalid tenant IDs, traversal, control characters, remote URLs, and foreign namespaces return null.
     *
     * @param mixed $path Local storage path
     * @param string|null $tenant Tenant ID or null for the current tenant
     * @return string|null Canonical path or null if it is invalid
     */
    public static function normalizePath( mixed $path, ?string $tenant = null ) : ?string
    {
        try {
            $tenant = Tenancy::check( $tenant ?? Tenancy::value() );
        } catch( \InvalidArgumentException ) {
            return null;
        }

        if( !is_string( $path ) || $path === '' || str_contains( $path, '..' )
            || str_contains( $path, '\\' ) || preg_match( '/\p{C}/u', $path ) !== 0 ) {
            return null;
        }

        $path = implode( '/', array_filter(
            explode( '/', $path ),
            static fn( string $part ) : bool => $part !== '' && $part !== '.',
        ) );
        $prefix = self::prefix( $tenant );

        if( !str_starts_with( $path, $prefix ) ) {
            return null;
        }

        $relative = substr( $path, strlen( $prefix ) );
        $parts = explode( '/', $relative, 2 );

        if( count( $parts ) !== 2 || !Str::isUuid( $parts[0] ) || $parts[1] === '' ) {
            return null;
        }

        return $path;
    }


    /**
     * Returns the configured Laravel storage disk.
     *
     * @param string $disk Logical disk name, "public" or "private"
     */
    public static function disk( string $disk ) : FilesystemAdapter
    {
        return Storage::disk( self::diskName( $disk ) );
    }


    /**
     * Returns the configured Laravel storage disk name.
     */
    public static function diskName( string $disk ) : string
    {
        if( !in_array( $disk, ['public', 'private'], true ) ) {
            throw new Exception( sprintf( 'Invalid file disk "%s"', $disk ) );
        }

        $name = (string) config( "cms.disks.{$disk}.name", $disk === 'private' ? 'local' : 'public' );

        if( $disk === 'private' && $name === (string) config( 'cms.disks.public.name', 'public' ) ) {
            throw new Exception( 'Public and private file disks must be different' );
        }

        return $name;
    }

    /**
     * Returns the File UUID owning a canonical managed path.
     *
     * @param string $tenant Tenant namespace
     * @param mixed $path Candidate storage path
     * @return string|null Owner UUID as stored in the path, or null for invalid, remote, legacy, or foreign paths
     */
    public static function owner( string $tenant, mixed $path ) : ?string
    {
        if( !( $path = self::normalizePath( $path, $tenant ) ) ) {
            return null;
        }

        return explode( '/', substr( $path, strlen( self::prefix( $tenant ) ) ), 2 )[0];
    }

    /**
     * Tests whether a managed path belongs to a File UUID without changing either representation.
     */
    public static function owns( string $tenant, string $id, mixed $path ) : bool
    {
        $owner = self::owner( $tenant, $path );

        return $owner !== null && strcasecmp( $owner, $id ) === 0;
    }

    /**
     * Ensures a client-supplied file path stays within the current tenant's storage or is a system URL.
     *
     * @param string|null $path Storage path or URL provided by the caller
     * @return ($path is null ? null : string) The validated path or null if none was given
     * @throws Exception If the path escapes the tenant's storage directory
     */
    public static function checkPath( ?string $path ) : ?string
    {
        if( $path === null ) {
            return null;
        }

        $value = str_starts_with( $path, 'http' )
            ? ( Utils::isValidUrl( $path, strict: false ) ? $path : null )
            : self::normalizePath( $path );

        if( $value === null ) {
            throw new Exception( sprintf( 'Invalid file path "%s"', $path ) );
        }

        return $value;
    }


    /**
     * Runs a callback while backup/restore and tenant media mutations are serialized.
     *
     * @template T
     * @param string $tenant Tenant ID owning the storage namespace
     * @param \Closure(): T $callback Work that reads or changes the media catalog
     * @param int $wait Maximum seconds to wait for the tenant gate
     * @return T Callback result
     */
    public static function storageLock( string $tenant, \Closure $callback, int $wait = 30 ) : mixed
    {
        $id = hash( 'sha256', $tenant );
        $owner = new \stdClass();

        try {
            return Cache::lock( 'cms_storage_' . $id, 86400 )->block( $wait, function() use (
                $callback, $id, $owner
            ) {
                Hooks::get()->storage[$id] = ['owner' => $owner, 'callbacks' => []];
                return $callback();
            } );
        } finally {
            $state = Hooks::get()->storage[$id] ?? null;

            if( $state && $state['owner'] === $owner )
            {
                unset( Hooks::get()->storage[$id] );

                foreach( $state['callbacks'] as $deferred ) {
                    try {
                        $deferred();
                    } catch( \Throwable $e ) {
                        report( $e );
                    }
                }
            }
        }
    }

    /**
     * Runs work after the active tenant storage gate is released.
     */
    public static function deferStorage( string $tenant, \Closure $callback ): void
    {
        $key = hash( 'sha256', $tenant );

        if( isset( Hooks::get()->storage[$key] ) ) {
            Hooks::get()->storage[$key]['callbacks'][] = $callback;
        } else {
            $callback();
        }
    }
}
