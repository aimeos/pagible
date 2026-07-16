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
     * @return array<string, array<string, mixed>> Map with "panels" and "subpanels" keys
     */
    public static function all() : array
    {
        return [
            'panels' => self::$panels,
            'subpanels' => self::$subpanels,
        ];
    }


    /**
     * Registers an admin panel extension.
     *
     * A top-level key like "products" adds a navigation panel and requires a "permission".
     * A sub-panel key like "page:settings" adds a tab to the page, element or file editor
     * and inherits the host view's permission.
     *
     * @param string $key Panel key, e.g. "products" or "page:settings"
     * @param array<string, string> $definition Definition with "label", "component" and optional "icon"/"permission"
     * @throws \InvalidArgumentException If the key, definition or component URL is invalid
     * @throws \LogicException If the key is already registered
     */
    public static function register( string $key, array $definition ) : void
    {
        if( empty( $definition['label'] ) ) {
            throw new \InvalidArgumentException( "Plugin '$key' requires a 'label'" );
        }

        self::url( $key, $definition['component'] ?? null );

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

        if( isset( self::$panels[$key] ) ) {
            throw new \LogicException( "Plugin '$key' is already registered" );
        }

        $panel = [
            'label' => $definition['label'],
            'permission' => $definition['permission'],
            'component' => $definition['component'],
        ];

        if( !empty( $definition['icon'] ) ) {
            $panel['icon'] = $definition['icon'];
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

        if( isset( self::$subpanels[$host][$name] ) ) {
            throw new \LogicException( "Plugin '$key' is already registered" );
        }

        self::$subpanels[$host][$name] = [
            'label' => $definition['label'],
            'component' => $definition['component'],
        ];
    }


    /**
     * Validates the component URL of a panel.
     *
     * @param string $key Panel key for error messages
     * @param string|null $url Component URL to validate
     * @throws \InvalidArgumentException If the URL is missing or unsafe
     */
    private static function url( string $key, ?string $url ) : void
    {
        if( empty( $url ) ) {
            throw new \InvalidArgumentException( "Plugin '$key' requires a 'component'" );
        }

        if( $url[0] !== '/' || str_starts_with( $url, '//' ) || str_contains( $url, '..' ) ) {
            throw new \InvalidArgumentException( "Invalid component URL '$url' for plugin '$key'" );
        }
    }
}
