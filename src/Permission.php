<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use \Illuminate\Contracts\Auth\Authenticatable;


/**
 * Permission class.
 */
class Permission
{
    /**
     * Available permission names
     *
     * @var array<int, string>
     */
    private static array $can = [
        'page:view',
        'page:save',
        'page:add',
        'page:drop',
        'page:keep',
        'page:purge',
        'page:publish',
        'page:move',
        'page:metrics',
        'page:synthesize',
        'page:refine',

        'element:view',
        'element:save',
        'element:add',
        'element:drop',
        'element:keep',
        'element:purge',
        'element:publish',

        'file:view',
        'file:save',
        'file:add',
        'file:drop',
        'file:keep',
        'file:purge',
        'file:publish',
        'file:describe',

        'config:page',

        'audio:transcribe',

        'image:imagine',
        'image:inpaint',
        'image:isolate',
        'image:repaint',
        'image:erase',
        'image:uncrop',
        'image:upscale',

        'text:translate',
        'text:write',
    ];

    /**
     * Anonymous callback which allows or denies actions.
     */
    private static ?\Closure $canCallback = null;

    /**
     * Anonymous callback which adds permissions.
     */
    private static ?\Closure $addCallback = null;

    /**
     * Anonymous callback which removes permissions.
     */
    private static ?\Closure $removeCallback = null;


    /**
     * Adds the permission for the requested action to the user.
     *
     * @param array<string>|string $action Name(s) of the requested action(s), e.g. "page:view"
     * @param Authenticatable $user Laravel user object
     * @return Authenticatable Updated Laravel user object with the new permission
     */
    public static function add( array|string $action, Authenticatable $user ) : Authenticatable
    {
        if( $closure = self::$addCallback ) {
            return $closure( $action, $user );
        }

        $actions = array_filter( (array) $action, function( $entry ) {
            return str_contains( $entry, ':' ) || array_key_exists( $entry, config( 'cms.roles', [] ) );
        } );

        // @phpstan-ignore-next-line property.notFound
        $user->cmsperms = array_values( array_unique( array_merge( $user->cmsperms ?? [], $actions ) ) );

        return $user;
    }


    /**
     * Sets the callback for adding permissions.
     *
     * @param \Closure|null $callback Anonymous function or NULL to reset
     */
    public static function addUsing( ?\Closure $callback ) : void
    {
        self::$addCallback = $callback;
    }


    /**
     * Returns the list of all available actions.
     *
     * @return array<int, string> List of action names
     */
    public static function all(): array
    {
        return self::$can;
    }


    /**
     * Checks if the user has the permission for the requested action.
     *
     * @param string $action Name of the requested action, e.g. "page:view"
     * @param Authenticatable|null $user Laravel user object
     * @return bool TRUE of the user is allowed to perform the action, FALSE if not
     */
    public static function can( string $action, ?Authenticatable $user ) : bool
    {
        if( $closure = self::$canCallback ) {
            return $closure( $action, $user );
        }

        $perms = $user->cmsperms ?? [];

        if( $action === '*' ) {
            return !empty( $perms );
        }

        return in_array( $action, self::resolve( $perms ) );
    }


    /**
     * Sets the callback for checking permissions.
     *
     * @param \Closure|null $callback Anonymous function or NULL to reset
     */
    public static function canUsing( ?\Closure $callback ) : void
    {
        self::$canCallback = $callback;
    }


    /**
     * Returns the available actions and their permissions.
     *
     * @param Authenticatable|null $user Laravel user object
     * @return array<string, bool> List of actions as keys and booleans as values indicating if the user has permission for the action
     */
    public static function get( ?Authenticatable $user ) : array
    {
        $map = [];

        foreach( self::$can as $action ) {
            $map[$action] = self::can( $action, $user );
        }

        return $map;
    }


    /**
     * Registers additional permission names.
     *
     * @param array<string>|string $actions Permission name(s) to register
     */
    public static function register( array|string $actions ) : void
    {
        foreach( (array) $actions as $action )
        {
            if( !in_array( $action, self::$can ) ) {
                self::$can[] = $action;
            }
        }
    }


    /**
     * Returns the expanded permissions for a named role.
     *
     * @param string $name Role name
     * @return array<int, string> List of resolved permission names
     */
    public static function role( string $name ) : array
    {
        return self::resolve( config( "cms.roles.{$name}", [] ) );
    }


    /**
     * Returns the available role names from config.
     *
     * @return array<int, string> List of role names
     */
    public static function roles() : array
    {
        return array_keys( config( 'cms.roles', [] ) );
    }


    /**
     * Removes the permission for the requested action from the user.
     *
     * @param array<string>|string $action Name(s) of the requested action(s), e.g. "page:view"
     * @param Authenticatable $user Laravel user object
     * @return Authenticatable Updated Laravel user object with the removed permission
     */
    public static function remove( array|string $action, Authenticatable $user ) : Authenticatable
    {
        if( $closure = self::$removeCallback ) {
            return $closure( $action, $user );
        }

        // @phpstan-ignore-next-line property.notFound
        $user->cmsperms = array_values( array_diff( $user->cmsperms ?? [], (array) $action ) );

        return $user;
    }


    /**
     * Sets the callback for removing permissions.
     *
     * @param \Closure|null $callback Anonymous function or NULL to reset
     */
    public static function removeUsing( ?\Closure $callback ) : void
    {
        self::$removeCallback = $callback;
    }


    /**
     * Resolves roles and wildcards to concrete permission strings.
     *
     * @param array<int, string> $entries Permission and/or role entries
     * @return array<int, string> Resolved permission names
     */
    private static function resolve( array $entries ) : array
    {
        $perms = [];
        $deny = [];

        foreach( $entries as $entry )
        {
            if( str_starts_with( $entry, '!' ) ) {
                array_push( $deny, ...self::resolve( [substr( $entry, 1 )] ) );
            } elseif( $entry === '*' ) {
                array_push( $perms, ...self::$can );
            } elseif( !str_contains( $entry, ':' ) ) {
                array_push( $perms, ...self::resolve( config( "cms.roles.{$entry}", [] ) ) );
            } elseif( str_contains( $entry, '*' ) ) {
                [$prefix, $suffix] = explode( ':', $entry, 2 );
                foreach( self::$can as $perm ) {
                    [$p, $s] = explode( ':', $perm, 2 );
                    if( ( $prefix === '*' || $p === $prefix ) && ( $suffix === '*' || $s === $suffix ) ) {
                        $perms[] = $perm;
                    }
                }
            } else {
                $perms[] = $entry;
            }
        }

        return $deny ? array_values( array_diff( $perms, $deny ) ) : $perms;
    }
}
