<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Permission;


/**
 * CMS roles and permissions of the application user model.
 *
 * Casts the "cmsperms" column also for new and made users and gives access to
 * the assigned roles and permissions. Checks use the Laravel Gate, e.g.
 * $user->can( 'page:publish' ).
 */
trait HasCmsPermissions
{
    /**
     * Returns the CMS roles, permissions and deny entries assigned to the user.
     *
     * @return array<int, string> Assigned entries, e.g. ['editor', '!page:drop']
     */
    public function cmsPermissions() : array
    {
        return Permission::assigned( $this );
    }


    /**
     * Replaces the CMS roles, permissions and deny entries of the stored user.
     *
     * The acting user isn't checked, see Permission::set().
     *
     * @param iterable<mixed> $entries Roles, permissions and deny entries
     * @return array<int, string> Assigned entries
     */
    public function setCmsPermissions( iterable $entries ) : array
    {
        return Permission::set( $this, $entries );
    }


    /**
     * Adds the cast of the "cmsperms" column.
     */
    protected function initializeHasCmsPermissions() : void
    {
        $this->mergeCasts( ['cmsperms' => 'array'] );
    }
}
