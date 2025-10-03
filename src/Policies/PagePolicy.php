<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Policies;

use Aimeos\Cms\Models\Page;
use App\Models\User;


class PagePolicy
{
    /**
     * Determine if pages can be added by the user.
     */
    public function add( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:add', $user );
    }


    /**
     * Determine if pages can be dropped by the user.
     */
    public function drop( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:drop', $user );
    }


    /**
     * Determine if pages can be restored by the user.
     */
    public function keep( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:keep', $user );
    }


    /**
     * Determine if pages can be moved by the user.
     */
    public function move( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:move', $user );
    }


    /**
     * Determine if pages version can be published by the user.
     */
    public function publish( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:publish', $user );
    }


    /**
     * Determine if pages can be purged by the user.
     */
    public function purge( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:purge', $user );
    }


    /**
     * Determine if pages can be saved by the user.
     */
    public function save( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:save', $user );
    }


    /**
     * Determine if pages can be viewed by the user.
     */
    public function view( User $user ): bool
    {
        return \Aimeos\Cms\Permission::can( 'page:view', $user );
    }
}