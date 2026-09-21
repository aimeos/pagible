<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Tenancy;


/**
 * Validates tenant arguments and lists the tenants of subscriptions which only the tenants can fix,
 * so operators know whom to notify.
 */
trait HandlesTenants
{
    /** Maximum number of listed tenants to keep the output readable */
    private const TENANTS_MAX = 20;


    /**
     * Returns the validated tenant ID or NULL after printing why it's invalid.
     *
     * @param string $name Name of the argument or option in the error message
     */
    private function tenant( mixed $value, string $name ) : ?string
    {
        if( !is_string( $value ) ) {
            $this->error( sprintf( 'The %s must be a string.', $name ) );
            return null;
        }

        try {
            return Tenancy::check( $value );
        } catch( \InvalidArgumentException $e ) {
            $this->error( $e->getMessage() );
            return null;
        }
    }


    /**
     * Returns the tenants with the most subscriptions first, e.g. "shop-a (2), shop-b (1)".
     *
     * @param array<array-key, int> $counts Number of subscriptions by tenant ID
     * @return string|null List of tenants or NULL if there's only the default tenant
     */
    private function tenants( array $counts ) : ?string
    {
        // Single-tenant setups only use the default tenant, so there's nobody else to notify
        if( array_keys( $counts ) === [''] ) {
            return null;
        }

        $list = [];

        // Numeric tenant IDs are converted to integer array keys by PHP
        foreach( $counts as $tenant => $count ) {
            $list[] = [(string) $tenant, $count];
        }

        usort( $list, fn( array $a, array $b ) => [$b[1], $a[0]] <=> [$a[1], $b[0]] );

        $names = array_map(
            // Brackets aren't allowed in tenant IDs, so the default tenant can't be confused with others
            fn( array $entry ) => ( $entry[0] !== '' ? $entry[0] : '[default]' ) . ' (' . $entry[1] . ')',
            array_slice( $list, 0, self::TENANTS_MAX )
        );

        if( ( $more = count( $list ) - self::TENANTS_MAX ) > 0 ) {
            return implode( ', ', $names ) . sprintf( ' and %d more', $more );
        }

        return implode( ', ', $names );
    }
}
