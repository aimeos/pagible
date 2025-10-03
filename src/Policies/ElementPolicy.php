<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Policies;

use Aimeos\Cms\Models\Element;
use App\Models\User;


class ElementPolicy
{
    /**
     * Determine if elements can be added by the user.
     */
    public function add( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:add', $user );
    }


    /**
     * Determine if elements can be dropped by the user.
     */
    public function drop( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:drop', $user );
    }


    /**
     * Determine if elements can be restored by the user.
     */
    public function keep( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:keep', $user );
    }


    /**
     * Determine if elements can be published by the user.
     */
    public function publish( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:publish', $user );
    }


    /**
     * Determine if elements can be purged by the user.
     */
    public function purge( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:purge', $user );
    }


    /**
     * Determine if elements can be updated by the user.
     */
    public function save( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:save', $user );
    }


    /**
     * Determine if elements can be viewed by the user.
     */
    public function view( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'element:view', $user );
    }
}
