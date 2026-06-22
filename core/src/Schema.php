<?php

/**
 * @license MIT, https://opensource.org/license/mit
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
        // Tenant-scope the cache key: themes/schemas are per-tenant (see discover()), so a
        // process-global static must not serve one tenant's schema to another under Octane.
        $key = Tenancy::value() . '/' . ( $name ?? '*' ) . '/' . ( $section ?? '*' );

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
                    foreach( $theme[$sec] as $entry => $value ) {
                        $result[$entry] = $result[$entry] ?? $value;
                    }
                }
                else
                {
                    foreach( $theme[$sec] as $entry => $value ) {
                        $result[$sec][$entry] = $result[$sec][$entry] ?? $value;
                    }
                }
            }
        }

        return self::$schemas[$key] = $result;
    }


    /**
     * Returns the default (first) section defined for a page type, or "main".
     *
     * @param string|null $type Page type
     * @return string Default section name
     */
    public static function section( ?string $type = null ) : string
    {
        return self::sections( $type )[0] ?? 'main';
    }


    /**
     * Returns the content section names defined for a page type in schema.json.
     *
     * Sections are the layout regions a page type exposes (e.g. "main", "footer")
     * and are used as the valid "group" values of its content elements. Results are
     * merged across all registered themes.
     *
     * @param string|null $type Page type or null for all page types
     * @return array<int, string> Section names
     */
    public static function sections( ?string $type = null ) : array
    {
        $result = [];

        foreach( self::all() as $theme )
        {
            $types = $theme['types'] ?? [];
            $defs = $type !== null ? [$types[$type] ?? []] : array_values( $types );

            foreach( $defs as $def )
            {
                foreach( (array) ( $def['sections'] ?? [] ) as $section ) {
                    $result[$section] = true;
                }
            }
        }

        return array_keys( $result );
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
     * @throws Exception If schema.json is missing or invalid
     */
    private static function meta( string $path ) : array
    {
        $file = $path . '/schema.json';

        if( !file_exists( $file ) ) {
            throw new Exception( sprintf( 'Missing schema.json in "%s"', $path ) );
        }

        $json = file_get_contents( $file );

        if( $json === false || strlen( $json ) > 1048576 ) {
            throw new Exception( sprintf( 'Invalid schema.json in "%s"', $path ) );
        }

        $data = json_decode( $json, true );

        if( !is_array( $data ) ) {
            throw new Exception( sprintf( 'Invalid JSON in schema.json at "%s"', $path ) );
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
