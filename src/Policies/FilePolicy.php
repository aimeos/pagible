<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Policies;

use Aimeos\Cms\Models\File;
use App\Models\User;


class FilePolicy
{
    /**
     * Determine if files can be added by the user.
     */
    public function add( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:add', $user );
    }


    /**
     * Determine if files can be dropped by the user.
     */
    public function drop( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:drop', $user );
    }


    /**
     * Determine if files can be restored by the user.
     */
    public function keep( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:keep', $user );
    }


    /**
     * Determine if files can be published by the user.
     */
    public function publish( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:publish', $user );
    }


    /**
     * Determine if files can be purged by the user.
     */
    public function purge( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:purge', $user );
    }


    /**
     * Determine if files can be updated by the user.
     */
    public function save( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:save', $user );
    }


    /**
     * Determine if files can be viewed by the user.
     */
    public function view( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'file:view', $user );
    }
}