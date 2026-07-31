<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    private const INDEXES = [
        'order_items' => ['cms_mollie_order_items_order' => ['order_id']],
        'payments' => ['cms_mollie_payments_source' => ['mollie_payment_id']],
        'orders' => ['cms_mollie_orders_source' => ['mollie_payment_id']],
    ];


    /**
     * Adds indexes used to resolve Mollie access sources from webhook payments.
     *
     * @throws \RuntimeException If the Cashier Mollie tables have not been published first
     */
    public function up(): void
    {
        $missing = array_values( array_filter(
            array_keys( self::INDEXES ),
            fn( string $table ) => !Schema::hasTable( $table ),
        ) );

        if( $missing !== [] ) {
            throw new \RuntimeException( sprintf(
                'Cashier Mollie migrations must run before Pagible access indexes: %s.',
                implode( ', ', $missing ),
            ) );
        }

        foreach( self::INDEXES as $table => $indexes )
        {
            $indexes = array_filter(
                $indexes,
                fn( array $columns, string $name ) => !Schema::hasIndex( $table, $name ),
                ARRAY_FILTER_USE_BOTH,
            );

            if( $indexes ) {
                Schema::table( $table, function( Blueprint $blueprint ) use ( $indexes ) {
                    foreach( $indexes as $name => $columns ) {
                        $blueprint->index( $columns, $name );
                    }
                } );
            }
        }
    }


    /**
     * Removes the Pagible-owned Mollie access indexes.
     */
    public function down(): void
    {
        foreach( self::INDEXES as $table => $indexes )
        {
            if( !Schema::hasTable( $table ) ) {
                continue;
            }

            $indexes = array_filter(
                $indexes,
                fn( array $columns, string $name ) => Schema::hasIndex( $table, $name ),
                ARRAY_FILTER_USE_BOTH,
            );

            if( $indexes ) {
                Schema::table( $table, function( Blueprint $blueprint ) use ( $indexes ) {
                    foreach( array_keys( $indexes ) as $name ) {
                        $blueprint->dropIndex( $name );
                    }
                } );
            }
        }
    }
};
