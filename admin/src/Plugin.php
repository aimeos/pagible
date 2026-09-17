<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Registry for admin panel extensions.
 *
 * Composer packages register top-level navigation panels ("products") and editor
 * sub-panels ("page:settings") whose Vue components are loaded by the admin SPA.
 */
class Plugin
{
    /**
     * Registered translation catalogs indexed by context.
     *
     * @var array<string, string>
     */
    private static array $i18n = [];

    /**
     * Registered top-level navigation panels indexed by key.
     *
     * @var array<string, array<string, string>>
     */
    private static array $panels = [];

    /**
     * Registered editor sub-panels indexed by host and key.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private static array $subpanels = [];


    /**
     * Returns all registered panels and sub-panels in registration order.
     *
     * @return array{
     *   i18n: array<string, string>,
     *   panels: array<string, array<string, string>>,
     *   subpanels: array<string, array<string, array<string, string>>>
     * }
     */
    public static function all() : array
    {
        return [
            'i18n' => self::$i18n,
            'panels' => self::$panels,
            'subpanels' => self::$subpanels,
        ];
    }


    /**
     * Registers a package-owned admin translation catalog.
     *
     * The URL must be a same-origin absolute path containing one "{locale}"
     * placeholder, which the admin replaces with its active language.
     *
     * @param string $key Translation context matching "[a-z0-9_-]+"
     * @param string $url Catalog URL, e.g. "/vendor/cms/example/i18n/{locale}.json"
     * @throws \InvalidArgumentException If the key or catalog URL is invalid
     * @throws \LogicException If the key is already registered differently
     */
    public static function i18n( string $key, string $url ) : void
    {
        if( !preg_match( '/^[a-z0-9_-]+$/', $key ) ) {
            throw new \InvalidArgumentException( "Invalid translation key '$key'" );
        }

        self::url( $key, $url, 'translation catalog' );

        if( substr_count( $url, '{locale}' ) !== 1 ) {
            throw new \InvalidArgumentException( "Translation catalog URL '$url' for plugin '$key' requires one '{locale}' placeholder" );
        }

        if( isset( self::$i18n[$key] ) ) {
            if( self::$i18n[$key] === $url ) {
                return;
            }

            throw new \LogicException( "Translation catalog '$key' is already registered" );
        }

        self::$i18n[$key] = $url;
    }


    /**
     * Registers an admin panel extension.
     *
     * A top-level key like "products" adds a navigation panel and requires a "permission".
     * Its component renders the panel body; the admin SPA provides the app bar, navigation
     * and main content layout. A sub-panel key like "page:settings" adds a tab to the page,
     * element or file editor and inherits the host view's permission.
     *
     * @param string $key Panel key, e.g. "products" or "page:settings"
     * @param array<string, string> $definition Definition with "label", "component" and optional "icon"/"i18n"/"permission"
     * @throws \InvalidArgumentException If the key, definition or component URL is invalid
     * @throws \LogicException If the key is already registered
     */
    public static function register( string $key, array $definition ) : void
    {
        if( empty( $definition['label'] ) ) {
            throw new \InvalidArgumentException( "Plugin '$key' requires a 'label'" );
        }

        self::url( $key, $definition['component'] ?? null, 'component' );

        if( isset( $definition['i18n'] ) && !isset( self::$i18n[$definition['i18n']] ) ) {
            throw new \InvalidArgumentException( "Unknown translation catalog '{$definition['i18n']}' for plugin '$key'" );
        }

        if( strpos( $key, ':' ) === false ) {
            self::panel( $key, $definition );
        } else {
            self::subpanel( $key, $definition );
        }
    }


    /**
     * Registers a top-level navigation panel.
     *
     * @param string $key Panel key matching "[a-z0-9_-]+"
     * @param array<string, string> $definition Panel definition
     * @throws \InvalidArgumentException If the key or required fields are invalid
     * @throws \LogicException If the key is already registered
     */
    private static function panel( string $key, array $definition ) : void
    {
        if( !preg_match( '/^[a-z0-9_-]+$/', $key ) ) {
            throw new \InvalidArgumentException( "Invalid plugin key '$key'" );
        }

        if( empty( $definition['permission'] ) ) {
            throw new \InvalidArgumentException( "Plugin '$key' requires a 'permission'" );
        }

        $panel = [
            'label' => $definition['label'],
            'permission' => $definition['permission'],
            'component' => $definition['component'],
        ];

        if( !empty( $definition['icon'] ) ) {
            $panel['icon'] = $definition['icon'];
        }

        if( isset( $definition['i18n'] ) ) {
            $panel['i18n'] = $definition['i18n'];
        }

        if( isset( self::$panels[$key] ) ) {
            if( self::$panels[$key] === $panel ) {
                return;
            }

            throw new \LogicException( "Plugin '$key' is already registered" );
        }

        self::$panels[$key] = $panel;
    }


    /**
     * Registers an editor sub-panel.
     *
     * @param string $key Sub-panel key like "page:settings"
     * @param array<string, string> $definition Sub-panel definition
     * @throws \InvalidArgumentException If the host or key is invalid
     * @throws \LogicException If the key is already registered
     */
    private static function subpanel( string $key, array $definition ) : void
    {
        if( !preg_match( '/^(page|element|file):[a-z0-9_-]+$/', $key ) ) {
            throw new \InvalidArgumentException( "Invalid sub-panel key '$key'" );
        }

        [$host, $name] = explode( ':', $key, 2 );

        $panel = [
            'label' => $definition['label'],
            'component' => $definition['component'],
        ];

        if( isset( $definition['i18n'] ) ) {
            $panel['i18n'] = $definition['i18n'];
        }

        if( isset( self::$subpanels[$host][$name] ) ) {
            if( self::$subpanels[$host][$name] === $panel ) {
                return;
            }

            throw new \LogicException( "Plugin '$key' is already registered" );
        }

        self::$subpanels[$host][$name] = $panel;
    }


    /**
     * Validates a same-origin URL of a plugin resource.
     *
     * @param string $key Panel key for error messages
     * @param string|null $url Resource URL to validate
     * @param string $field Resource name for error messages
     * @throws \InvalidArgumentException If the URL is missing or unsafe
     */
    private static function url( string $key, ?string $url, string $field ) : void
    {
        if( empty( $url ) ) {
            throw new \InvalidArgumentException( "Plugin '$key' requires a '$field'" );
        }

        $decoded = rawurldecode( $url );

        if( $decoded[0] !== '/' || str_starts_with( $decoded, '//' )
            || str_contains( $decoded, '..' ) || str_contains( $decoded, '\\' )
            || preg_match( '/[\x00-\x20\x7f]/', $decoded )
        ) {
            throw new \InvalidArgumentException( "Invalid $field URL '$url' for plugin '$key'" );
        }
    }
}
