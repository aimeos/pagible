<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\DB;
use Aimeos\Cms\Tenancy;


/**
 * Runs console commands for each tenant whose content is stored in the CMS tables.
 *
 * The using command must define a "--tenant=" option.
 *
 * @phpstan-require-extends \Illuminate\Console\Command
 */
trait Tenants
{
    /**
     * Calls the callback in the context of each tenant to process.
     *
     * @param non-empty-list<string> $tables Tables containing the tenants to process if no tenant is passed
     * @param \Closure(string): bool $callback Receives the tenant ID and returns FALSE on failure
     * @return bool TRUE if all tenants were processed successfully, FALSE otherwise
     */
    protected function eachTenant( array $tables, \Closure $callback ) : bool
    {
        if( ( $tenants = $this->tenants( $tables ) ) === null ) {
            return false;
        }

        $result = true;

        foreach( $tenants as $tenant ) {
            $result = Tenancy::run( $tenant, fn() => $callback( $tenant ) ) && $result;
        }

        return $result;
    }


    /**
     * Returns the tenants to process.
     *
     * @param non-empty-list<string> $tables Tables containing the tenants to process if no tenant is passed
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
                $this->error( sprintf( 'Run the command for other tenants using: php artisan tenants:run %s', $this->getName() ) );
                return null;
            }

            return [Tenancy::value()];
        }

        if( $tenant !== null ) {
            return [Tenancy::check( (string) $tenant )];
        }

        $db = DB::connection();
        $query = $db->table( array_shift( $tables ) )->select( 'tenant_id' )->distinct();

        foreach( $tables as $table ) {
            $query->union( $db->table( $table )->select( 'tenant_id' ) );
        }

        return $db->query()->fromSub( $query, 'tenants' )->orderBy( 'tenant_id' )->pluck( 'tenant_id' )->map( strval(...) )->all();
    }
}
