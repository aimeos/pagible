<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\DB;
use Aimeos\Cms\Tenancy;


/**
 * Selects the tenants processed by console commands with a "--tenant" option.
 */
trait ForTenants
{
    /**
     * Returns the passed tenant or all tenants with records in the tables.
     *
     * @param array<string> $tables Table names
     * @return array<string>|null List of tenant IDs or NULL if the tenant can't be processed
     */
    protected function tenants( array $tables ) : ?array
    {
        $tenant = $this->option( 'tenant' );

        // Stancl only allows operations in the tenant context it has initialized
        if( Tenancy::managed() )
        {
            if( $tenant !== null && $tenant !== Tenancy::value() )
            {
                $this->error( 'Run the command for other tenants using: php artisan tenants:run ' . $this->getName() );
                return null;
            }

            return [Tenancy::value()];
        }

        if( $tenant !== null ) {
            return [Tenancy::check( (string) $tenant )];
        }

        $db = DB::connection();
        $query = null;

        foreach( $tables as $table )
        {
            $select = $db->table( $table )->select( 'tenant_id' );
            $query = $query ? $query->union( $select ) : $select->distinct();
        }

        return $query?->orderBy( 'tenant_id' )->pluck( 'tenant_id' )->map( strval(...) )->all() ?? [];
    }
}
