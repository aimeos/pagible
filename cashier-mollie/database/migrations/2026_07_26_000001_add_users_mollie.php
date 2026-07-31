<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Adds missing Mollie customer and mandate identifiers to users.
     */
    public function up(): void
    {
        $customer = !Schema::hasColumn( 'users', 'mollie_customer_id' );
        $mandate = !Schema::hasColumn( 'users', 'mollie_mandate_id' );

        if( !$customer && !$mandate ) {
            return;
        }

        Schema::table( 'users', function( Blueprint $table ) use ( $customer, $mandate ) {
            if( $customer ) {
                $table->string( 'mollie_customer_id' )->nullable();
            }

            if( $mandate ) {
                $table->string( 'mollie_mandate_id' )->nullable();
            }
        } );
    }


    /**
     * Preserves shared Mollie columns and their local billing references.
     */
    public function down(): void
    {
        // Preserve columns that may predate this package and are still
        // referenced by local Cashier records after a package rollback.
    }
};
