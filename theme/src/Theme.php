<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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
     * Renders a CMS element or a reference to a shared element with the first existing view.
     *
     * @param array<string, mixed> $vars Variables of the parent view
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @param object $item The CMS element or a reference to a shared element
     * @return string The rendered CMS element
     */
    public static function element( array $vars, \Aimeos\Cms\Models\PageView $page, object $item ) : string
    {
        $item = cmsref( $page, $item );
        $names = [...self::names( $page, $item ), 'cms::invalid'];
        $files = cms( $page, 'files', collect() );

        if( $item instanceof \Aimeos\Cms\Models\Element ) {
            $files = $files->union( cms( $item, 'files', collect() ) );
            $item = (object) ['id' => cms($item, 'id'), 'type' => cms($item, 'type'), 'name' => cms($item, 'name'), 'data' => cms($item, 'data')];
        }

        $data = ['files' => $files];

        if( $action = Schema::action( $item->type ?? '' ) ) {
            $data['action'] = app()->call( $action, ['page' => $page, 'item' => $item] );
        }

        return View::first( $names, $data + (array) $item, $vars )->render();
    }


    /**
     * Returns the CSP hash of the given inline text.
     *
     * @param string $text Inline style or script text
     * @return string Hash like "'sha256-...'" or empty string if the text is empty
     */
    public static function hash( string $text ) : string
    {
        return $text !== '' ? "'sha256-" . base64_encode( hash( 'sha256', $text, true ) ) . "'" : '';
    }


    /**
     * Returns the CSP hashes of the page texts stored in the config of the page and its ancestors.
     *
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @param string $key Config key of the texts, e.g. "config.styles.data.text"
     * @return string Space separated hashes like "'sha256-...'"
     */
    public static function hashes( \Aimeos\Cms\Models\PageView $page, string $key ) : string
    {
        return $page->ancestorsAndSelf->map( fn( $item ) => self::hash( (string) cms( $item, $key ) ) )->filter()->implode( ' ' );
    }


    /**
     * Returns the headings of the content items with the item IDs used as anchors.
     *
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @param iterable<object> $items Content items including references to shared elements
     * @param array<int, int> $levels Heading levels to return, all if empty
     * @return array<int, array{id: string, level: int, title: string}> Headings in content order
     */
    public static function headings( \Aimeos\Cms\Models\PageView $page, iterable $items, array $levels = [] ) : array
    {
        $list = [];

        foreach( $items as $item )
        {
            $el = cmsref( $page, $item );
            $level = (int) ( $el->data->level ?? 2 );
            $title = (string) ( $el->data->title ?? '' );

            if( $title !== '' && in_array( $level, $levels, true ) ) {
                $list[] = ['id' => (string) ( $item->id ?? '' ), 'level' => $level, 'title' => $title];
            }
        }

        return $list;
    }


    /**
     * Sets the application locale for rendering pages in the given language.
     *
     * @param string $locale Language tag like "de" or "de-CH"
     */
    public static function locale( string $locale ) : void
    {
        App::setLocale( $locale );
        self::translations( $locale );
    }


    /**
     * Returns the view names to try for a CMS element, based on its type and the page theme.
     *
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @param object $item The CMS element with references already resolved
     * @return array<int, string> View names in the order they are tried, without the "cms::invalid" fallback
     */
    public static function names( \Aimeos\Cms\Models\PageView $page, object $item ) : array
    {
        if( !isset( $item->type ) ) {
            return [];
        }

        $type = (string) $item->type;

        [$theme, $type] = str_contains( $type, '::' )
            ? explode( '::', $type, 2 )
            : [cms( $page, 'theme' ) ?: 'cms', $type];

        return $theme === 'cms'
            ? ['cms::' . $type]
            : [$theme . '::' . $type, 'cms::' . $type];
    }


    /**
     * Returns the CSS rules of the theme variables configured for the page and its ancestors.
     *
     * Variables also defined by the "theme-dark" config of the page theme are applied
     * to the light mode only and the "theme-dark" values to the dark mode, all other
     * variables to both modes. The rules must be placed after the theme stylesheets.
     *
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @return string CSS rules or empty string if no variables are configured
     */
    public static function style( \Aimeos\Cms\Models\PageView $page ) : string
    {
        $vars = self::vars( $page );
        $modes = self::entry( $page, 'theme-dark' )[1];
        $css = self::rule( ':root:root', array_diff_key( $vars, $modes ) );

        foreach( ['light' => array_intersect_key( $vars, $modes ), 'dark' => self::vars( $page, 'theme-dark' )] as $mode => $list )
        {
            if( $rule = self::rule( ':root:not([data-theme=' . ( $mode === 'dark' ? 'light' : 'dark' ) . '])', $list ) ) {
                $css .= '@media (prefers-color-scheme: ' . $mode . '){' . $rule . '}' . self::rule( ':root[data-theme=' . $mode . ']', $list );
            }
        }

        return $css;
    }


    /**
     * Sets the language of the translations only, leaving the application locale unchanged.
     *
     * Regional languages without own translations (e.g. "de-CH") use the
     * translations of their base language ("de") instead of the fallback locale.
     *
     * @param string $locale Language tag like "de" or "de-CH"
     */
    public static function translations( string $locale ) : void
    {
        $translator = app( 'translator' );
        $translator->setLocale( $locale );

        $base = strtok( $locale, '-_' ) ?: $locale;
        $loader = $translator->getLoader();

        if( $base !== $locale && empty( $loader->load( $locale, '*', '*' ) ) && !empty( $loader->load( $base, '*', '*' ) ) ) {
            $translator->setLocale( $base );
        }
    }


    /**
     * Returns the validated theme variables configured for the page and its ancestors.
     *
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @param string $name Config entry of the page theme, e.g. "theme" or "theme-dark"
     * @param bool $defaults TRUE to include the schema defaults of variables not configured
     * @return array<string, string> CSS variable names as keys and their values, nearest page wins
     */
    public static function vars( \Aimeos\Cms\Models\PageView $page, string $name = 'theme', bool $defaults = false ) : array
    {
        [$key, $fields] = self::entry( $page, $name );
        $list = $page->ancestorsAndSelf->map( fn( $item ) => (array) cms( $item, 'config.' . $key . '.data' ) );
        $vars = [];

        if( $defaults ) {
            $list->prepend( array_map( fn( $field ) => $field['default'] ?? null, $fields ) );
        }

        foreach( $list as $data )
        {
            foreach( $data as $var => $value )
            {
                $type = $fields[$var]['type'] ?? null;
                $value = is_string( $value ) ? trim( $value ) : '';

                if( !$type || $value === '' || !preg_match( '/^--[a-z0-9-]+$/', (string) $var )
                    || ( $type === 'color' ? !preg_match( '/^#[0-9A-Fa-f]{3,8}$/', $value ) : preg_match( '#[<>{};\\\\\r\n]|/\*#', $value ) )
                ) {
                    continue;
                }

                // the breadcrumb divider is used as content and must be a CSS string
                if( $var === '--pico-nav-breadcrumb-divider' && !preg_match( '/^([\'"]).*\1$/', $value ) ) {
                    $value = '"' . str_replace( '"', '', $value ) . '"';
                }

                $vars[$var] = $value;
            }
        }

        return $vars;
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
        // synced and recursively cleaned up (see sync()). Reject anything
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
     * Returns the config key and fields of a config entry of the page theme.
     *
     * @param \Aimeos\Cms\Models\PageView $page The CMS page
     * @param string $name Config entry name without theme prefix
     * @return array{0: string, 1: array<string, array<string, mixed>>} Config key and field definitions
     */
    private static function entry( \Aimeos\Cms\Models\PageView $page, string $name ) : array
    {
        $theme = cms( $page, 'theme' ) ?: 'cms';
        $key = $theme === 'cms' ? $name : $theme . '::' . $name;

        return [$key, (array) ( Schema::get( $theme )['config'][$key]['fields'] ?? [] )];
    }


    /**
     * Returns a CSS rule for the given variables.
     *
     * @param string $selector CSS selector
     * @param array<string, string> $vars CSS variable names as keys and their values
     * @return string CSS rule or empty string if no variables are passed
     */
    private static function rule( string $selector, array $vars ) : string
    {
        $list = array_map( fn( $var, $value ) => $var . ':' . $value, array_keys( $vars ), $vars );
        return $list ? $selector . '{' . implode( ';', $list ) . '}' : '';
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

        File::deleteDirectory( $dir );
        File::ensureDirectoryExists( $dir );

        foreach( $disk->allFiles( $themePath . '/views' ) as $file )
        {
            $relative = substr( $file, strlen( $themePath . '/views/' ) );

            if( str_contains( $relative, '..' ) || str_contains( $relative, "\0" ) ) {
                continue;
            }

            $target = $dir . '/views/' . $relative;
            File::ensureDirectoryExists( dirname( $target ) );
            file_put_contents( $target, $disk->get( $file ) );
        }

        file_put_contents( $versionFile, (string) $version );
    }
}
