<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use \App\Models\User;


/**
 * Permission class.
 */
class Permission
{
    /**
     * Action permissions
     */
    private static $can = [
        'page:view'       => 0b00000000000000000000000000000001,
        'page:save'       => 0b00000000000000000000000000000010,
        'page:add'        => 0b00000000000000000000000000000100,
        'page:drop'       => 0b00000000000000000000000000001000,
        'page:keep'       => 0b00000000000000000000000000010000,
        'page:purge'      => 0b00000000000000000000000000100000,
        'page:publish'    => 0b00000000000000000000000001000000,
        'page:move'       => 0b00000000000000000000000010000000,

        'element:view'    => 0b00000000000000000000000100000000,
        'element:save'    => 0b00000000000000000000001000000000,
        'element:add'     => 0b00000000000000000000010000000000,
        'element:drop'    => 0b00000000000000000000100000000000,
        'element:keep'    => 0b00000000000000000001000000000000,
        'element:purge'   => 0b00000000000000000010000000000000,
        'element:publish' => 0b00000000000000000100000000000000,

        'file:view'       => 0b00000000000000010000000000000000,
        'file:save'       => 0b00000000000000100000000000000000,
        'file:add'        => 0b00000000000001000000000000000000,
        'file:drop'       => 0b00000000000010000000000000000000,
        'file:keep'       => 0b00000000000100000000000000000000,
        'file:purge'      => 0b00000000001000000000000000000000,
        'file:publish'    => 0b00000000010000000000000000000000,
    ];

    /**
     * Anonymous callback which allows or denies actions.
     */
    public static ?\Closure $callback = null;


    /**
     * Checks if the user has the permission for the requested action.
     *
     * @param string action Name of the requested action, e.g. "page:view"
     * @param \App\Models\User|null $user Laravel user object
     * @return bool TRUE of the user is allowed to perform the action, FALSE if not
     */
    public static function can( string $action, ?User $user ) : bool
    {
        if( $closure = self::$callback ) {
            return $closure( $action, $user );
        }

        if( $action === '*' ) {
            return $user && $user->cmseditor > 0;
        }

        return $user && isset( self::$can[$action] ) && self::$can[$action] & $user->cmseditor;
    }


    /**
     * Returns the available actions and their permissions.
     *
     * @param \App\Models\User|null $user Laravel user object
     * @return array List of actions as keys and booleans as values indicating if the user has permission for the action
     */
    public static function get( ?User $user ) : array
    {
        $map = [];

        foreach( self::$can as $action => $bit ) {
            $map[$action] = self::can( $action, $user );
        }

        return $map;
    }
}
