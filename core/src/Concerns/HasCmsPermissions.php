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
     * @param array<mixed>|null|false $langs Writable languages, NULL for unrestricted or FALSE to keep the stored ones
     * @return array<int, string> Assigned entries
     */
    public function setCmsPermissions( iterable $entries, array|null|false $langs = false ) : array
    {
        return Permission::set( $this, $entries, $langs );
    }


    /**
     * Adds the cast of the "cmsperms" column.
     *
     * New users get the database default of the "cmslangs" column (unrestricted). It has no cast
     * because database NULL and JSON null must be distinguishable. Loaded users replace all
     * attributes, so unselected columns stay missing and deny language-restricted writes.
     */
    protected function initializeHasCmsPermissions() : void
    {
        $this->mergeCasts( ['cmsperms' => 'array'] );

        if( !array_key_exists( 'cmslangs', $this->attributes ) ) {
            $this->attributes['cmslangs'] = null;
        }
    }
}
