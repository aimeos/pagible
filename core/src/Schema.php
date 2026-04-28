<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


/**
 * Schema registry class.
 */
class Schema
{
    /**
     * Cached schema results.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $schemas = [];

    /**
     * Registered themes (Composer packages).
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $themes = [];


    /**
     * Returns all registered and discovered themes merged.
     *
     * @return array<string, array<string, mixed>> All available themes
     */
    public static function all() : array
    {
        return array_merge( self::discover(), self::$themes );
    }


    /**
     * Returns a single theme by name.
     *
     * @param string $name Theme name
     * @return array<string, mixed>|null Theme data or null
     */
    public static function get( string $name ) : ?array
    {
        return self::$themes[$name] ?? self::discover()[$name] ?? null;
    }


    /**
     * Registers a theme from a given path.
     *
     * @param string $path Base path of the theme
     * @param string $name Theme identifier
     */
    public static function register( string $path, string $name ) : void
    {
        $data = self::meta( $path );
        $data['path'] = $path;

        if( $name !== 'cms' ) {
            $data = self::prefix( $data, $name );
        }

        self::$themes[$name] = $data;
        self::$schemas = [];
    }


    /**
     * Returns flattened schemas across all themes.
     *
     * Core keys are un-prefixed, non-core keys are prefixed with "{name}::".
     *
     * @param string|null $name Theme name filter or null for all
     * @param string|null $section Schema section filter (content, meta, config) or null for all
     * @return array<string, mixed> Flattened schemas
     */
    public static function schemas( ?string $name = null, ?string $section = null ) : array
    {
        $key = ( $name ?? '*' ) . '/' . ( $section ?? '*' );

        if( isset( self::$schemas[$key] ) ) {
            return self::$schemas[$key];
        }

        $result = [];
        $themes = $name !== null ? [$name => self::get( $name )] : self::all();
        $sections = $section ? [$section] : ['content', 'meta', 'config'];

        foreach( $themes as $themeName => $theme )
        {
            if( !$theme ) {
                continue;
            }

            foreach( $sections as $sec )
            {
                if( empty( $theme[$sec] ) ) {
                    continue;
                }

                if( $section !== null )
                {
                    foreach( $theme[$sec] as $key => $value ) {
                        $result[$key] = $result[$key] ?? $value;
                    }
                }
                else
                {
                    foreach( $theme[$sec] as $key => $value ) {
                        $result[$sec][$key] = $result[$sec][$key] ?? $value;
                    }
                }
            }
        }

        return self::$schemas[$key] = $result;
    }


    /**
     * Discovers tenant themes from the configured storage disk.
     *
     * @return array<string, array<string, mixed>> Discovered themes keyed by name
     */
    private static function discover() : array
    {
        $diskName = config( 'cms.theme.disk' );

        if( !$diskName ) {
            return [];
        }

        $ttl = config( 'cms.theme.cache-ttl', 0 );

        return Cache::remember( 'cms-themes_' . Tenancy::value(), $ttl, function() use ( $diskName ) {
            $themes = [];
            $disk = Storage::disk( $diskName );

            foreach( $disk->directories( '' ) as $dir )
            {
                $name = basename( $dir );

                if( !preg_match( '/^[a-zA-Z0-9-]+$/', $name ) ) {
                    continue;
                }

                if( isset( self::$themes[$name] ) ) {
                    continue;
                }

                if( !$disk->exists( $dir . '/schema.json' ) ) {
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

                    $themes[$name] = self::prefix( $data, $name );
                }
                catch( \Throwable $e )
                {
                    Log::warning( sprintf( 'Error discovering theme "%s" on disk "%s": %s', $name, $diskName, $e->getMessage() ) );
                    continue;
                }
            }

            return $themes;
        } );
    }


    /**
     * Reads and validates schema.json from the given path.
     *
     * @param string $path Base path of the theme
     * @return array<string, mixed> Parsed theme metadata
     * @throws \RuntimeException If schema.json is missing or invalid
     */
    private static function meta( string $path ) : array
    {
        $file = $path . '/schema.json';

        if( !file_exists( $file ) ) {
            throw new \RuntimeException( sprintf( 'Missing schema.json in "%s"', $path ) );
        }

        $json = file_get_contents( $file );

        if( $json === false || strlen( $json ) > 1048576 ) {
            throw new \RuntimeException( sprintf( 'Invalid schema.json in "%s"', $path ) );
        }

        $data = json_decode( $json, true );

        if( !is_array( $data ) ) {
            throw new \RuntimeException( sprintf( 'Invalid JSON in schema.json at "%s"', $path ) );
        }

        if( isset( $data['website'] ) && !Utils::isValidUrl( $data['website'] ) ) {
            unset( $data['website'] );
        }

        $data['preview'] = file_exists( $path . '/preview.webp' ) ? $path . '/preview.webp' : null;

        return $data;
    }


    /**
     * Prefixes schema keys with the theme name for non-core themes.
     *
     * @param array<string, mixed> $data Theme data
     * @param string $name Theme name
     * @return array<string, mixed> Theme data with prefixed keys
     */
    private static function prefix( array $data, string $name ) : array
    {
        foreach( ['content', 'meta', 'config'] as $section )
        {
            if( !empty( $data[$section] ) )
            {
                $namespaced = [];

                foreach( $data[$section] as $key => $value ) {
                    $namespaced[$name . '::' . $key] = $value;
                }

                $data[$section] = $namespaced;
            }
        }

        return $data;
    }
}
