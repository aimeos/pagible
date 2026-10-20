<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Database\Factories;


/**
 * CMS state of the application user factory.
 *
 * User::factory()->cms( 'editor', '!page:drop' )->create() creates an editor who
 * can't trash pages. The entries are stored directly, use Permission::set() to
 * validate them.
 */
trait HasCmsPermissions
{
    /**
     * Assigns the CMS roles, permissions and deny entries.
     *
     * @param string ...$perms Roles, permissions and deny entries, e.g. "editor" or "!page:drop"
     * @return static Factory for fluent interface
     */
    public function cms( string ...$perms ) : static
    {
        return $this->state( ['cmsperms' => array_values( $perms )] );
    }
}
