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
        'page:view'        => 0b00000000_00000000_00000000_00000001,
        'page:save'        => 0b00000000_00000000_00000000_00000010,
        'page:add'         => 0b00000000_00000000_00000000_00000100,
        'page:drop'        => 0b00000000_00000000_00000000_00001000,
        'page:keep'        => 0b00000000_00000000_00000000_00010000,
        'page:purge'       => 0b00000000_00000000_00000000_00100000,
        'page:publish'     => 0b00000000_00000000_00000000_01000000,
        'page:move'        => 0b00000000_00000000_00000000_10000000,

        'element:view'     => 0b00000000_00000000_00000001_00000000,
        'element:save'     => 0b00000000_00000000_00000010_00000000,
        'element:add'      => 0b00000000_00000000_00000100_00000000,
        'element:drop'     => 0b00000000_00000000_00001000_00000000,
        'element:keep'     => 0b00000000_00000000_00010000_00000000,
        'element:purge'    => 0b00000000_00000000_00100000_00000000,
        'element:publish'  => 0b00000000_00000000_01000000_00000000,

        'file:view'        => 0b00000000_00000001_00000000_00000000,
        'file:save'        => 0b00000000_00000010_00000000_00000000,
        'file:add'         => 0b00000000_00000100_00000000_00000000,
        'file:drop'        => 0b00000000_00001000_00000000_00000000,
        'file:keep'        => 0b00000000_00010000_00000000_00000000,
        'file:purge'       => 0b00000000_00100000_00000000_00000000,
        'file:publish'     => 0b00000000_01000000_00000000_00000000,
        'file:describe'    => 0b00000000_10000000_00000000_00000000,

        'config:page'      => 0b00000001_00000000_00000000_00000000,

        'page:metrics'     => 0b00000000_00000000_00000000_00000001_00000000_00000000_00000000_00000000,
        'page:synthesize'  => 0b00000000_00000000_00000000_00000010_00000000_00000000_00000000_00000000,
        'page:refine'      => 0b00000000_00000000_00000000_00000100_00000000_00000000_00000000_00000000,

        'text:translate'   => 0b00000000_00000000_00000000_01000000_00000000_00000000_00000000_00000000,
        'text:write'       => 0b00000000_00000000_00000000_10000000_00000000_00000000_00000000_00000000,

        'audio:transcribe' => 0b00000000_00000000_00000001_00000000_00000000_00000000_00000000_00000000,

        'image:imagine'    => 0b00000000_00000001_00000000_00000000_00000000_00000000_00000000_00000000,
        'image:inpaint'    => 0b00000000_00000010_00000000_00000000_00000000_00000000_00000000_00000000,
        'image:isolate'    => 0b00000000_00000100_00000000_00000000_00000000_00000000_00000000_00000000,
        'image:repaint'    => 0b00000000_00001000_00000000_00000000_00000000_00000000_00000000_00000000,
        'image:erase'      => 0b00000000_00010000_00000000_00000000_00000000_00000000_00000000_00000000,
        'image:uncrop'     => 0b00000000_00100000_00000000_00000000_00000000_00000000_00000000_00000000,
        'image:upscale'    => 0b00000000_01000000_00000000_00000000_00000000_00000000_00000000_00000000,
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
            return $user?->cmseditor > 0;
        }

        return (bool) ( ( self::$can[$action] ?? 0 ) & (int) $user?->cmseditor );
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
