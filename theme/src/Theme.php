<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;


/**
 * Theme view resolution class.
 */
class Theme
{
    /**
     * Discovers tenant themes from the configured storage disk.
     *
     * @return array<string, array<string, mixed>> Discovered themes keyed by name
     */
    public static function discover() : array
    {
        $diskName = config( 'cms.theme.disk' );

        if( !$diskName ) {
            return [];
        }

        $ttl = config( 'cms.theme.ttl', 0 );

        return Cache::remember( 'cms-themes_' . Tenancy::value(), $ttl, function() use ( $diskName ) {
            $themes = [];
            $disk = Storage::disk( $diskName );

            foreach( $disk->directories( '' ) as $dir )
            {
                $name = basename( $dir );

                if( !preg_match( '/^[a-zA-Z0-9-]+$/', $name ) || !$disk->exists( $dir . '/schema.json' ) ) {
                    continue;
                }

                try
                {
                    $json = $disk->get( $dir . '/schema.json' );

                    if( !is_string( $json ) || strlen( $json ) > 1048576 )
                    {
                        Log::warning( sprintf( 'Invalid schema.json for theme "%s" on disk "%s"', $name, $diskName ) );
                        continue;
                    }

                    $data = json_decode( $json, true );

                    if( !is_array( $data ) )
                    {
                        Log::warning( sprintf( 'Invalid JSON in schema.json for theme "%s" on disk "%s"', $name, $diskName ) );
                        continue;
                    }

                    $data['preview'] = $disk->exists( $dir . '/preview.webp' )
                        ? $disk->url( $dir . '/preview.webp' )
                        : null;

                    $themes[$name] = $data;
                }
                catch( \Throwable $e )
                {
                    Log::warning( sprintf( 'Error discovering theme "%s" on disk "%s": %s', $name, $diskName, $e->getMessage() ) );
                }
            }

            return $themes;
        } );
    }


    /**
     * Returns the layout types for a theme.
     *
     * @param string $name Theme name
     * @return array<string, mixed> Layout types from schema.json
     */
    public static function layouts( string $name ) : array
    {
        return Schema::get( $name )['types'] ?? [];
    }


    /**
     * Resolves the view namespace for a theme.
     *
     * For Composer themes, registers the view namespace and returns the name.
     * For tenant themes, syncs views from shared disk and registers namespace.
     *
     * @param string $name Theme name
     * @return string View namespace
     */
    public static function views( string $name ) : string
    {
        $theme = Schema::get( $name );

        if( $theme && isset( $theme['path'] ) )
        {
            View::addNamespace( $name, $theme['path'] . '/views' );
            return $name;
        }

        // Tenant theme names are concatenated into a local storage path that is
        // synced and recursively cleaned up (see sync()/cleanup()). Reject anything
        // outside the strict identifier charset (same whitelist as Theme::discover())
        // so a crafted page theme can never traverse out of the cms-themes directory.
        if( !preg_match( '/^[a-zA-Z0-9-]+$/', $name ) ) {
            return $name;
        }

        $tenant = Tenancy::value();

        if( !$tenant || !config( 'cms.theme.disk' ) ) {
            return $name;
        }

        $ttl = config( 'cms.theme.ttl', 0 );
        $disk = Storage::disk( config( 'cms.theme.disk' ) );

        $version = Cache::remember( 'cms-theme-version_' . $tenant . '_' . $name, $ttl, function() use ( $name, $disk ) {
            try {
                return $disk->lastModified( $name . '/schema.json' );
            } catch( \Throwable $e ) {
                return 0;
            }
        } );

        if( !$version ) {
            return $name;
        }

        $dir = storage_path( 'app/cms-themes/' . $tenant . '/' . $name );

        self::sync( $disk, $name, $dir, $version );
        View::getFinder()->replaceNamespace( $name, $dir . '/views' );

        return $name;
    }


    /**
     * Recursively removes a directory and its contents.
     *
     * @param string $dir Directory path to remove
     */
    private static function cleanup( string $dir ) : void
    {
        if( !is_dir( $dir ) ) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach( $items as $item )
        {
            $path = $item->getPathname();
            $item->isDir() ? rmdir( $path ) : unlink( $path );
        }

        rmdir( $dir );
    }


    /**
     * Syncs theme views from shared disk to local filesystem.
     *
     * @param \Illuminate\Contracts\Filesystem\Filesystem $disk Storage disk
     * @param string $themePath Remote theme path
     * @param string $dir Local destination path
     * @param int $version Remote version timestamp
     */
    private static function sync( $disk, string $themePath, string $dir, int $version ) : void
    {
        $versionFile = $dir . '/.version';

        if( file_exists( $versionFile ) && (int) file_get_contents( $versionFile ) === $version ) {
            return;
        }

        if( is_dir( $dir ) ) {
            self::cleanup( $dir );
        }

        $viewsDir = $dir . '/views';

        foreach( $disk->allFiles( $themePath . '/views' ) as $file )
        {
            $relative = substr( $file, strlen( $themePath . '/views/' ) );

            if( str_contains( $relative, '..' ) || str_contains( $relative, "\0" ) ) {
                continue;
            }

            $target = $viewsDir . '/' . $relative;
            $dir = dirname( $target );

            if( !is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }

            file_put_contents( $target, $disk->get( $file ) );
        }

        if( !is_dir( $dir ) ) {
            mkdir( $dir, 0755, true );
        }

        file_put_contents( $versionFile, (string) $version );
    }
}
