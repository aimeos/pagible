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
     * Adds the reserved payment-derived access projection without claiming an existing column.
     *
     * @throws \RuntimeException If the reserved users.access column already exists
     */
    public function up(): void
    {
        if( Schema::hasColumn( 'users', 'access' ) ) {
            throw new \RuntimeException( 'The users.access column is reserved by Pagible Cashier and already exists.' );
        }

        Schema::table( 'users', function( Blueprint $table ) {
            $table->json( 'access' )->nullable();
        } );
    }


    /**
     * Removes the payment-derived access projection created by this migration.
     */
    public function down(): void
    {
        if( !Schema::hasColumn( 'users', 'access' ) ) {
            return;
        }

        Schema::table( 'users', function( Blueprint $table ) {
            $table->dropColumn( 'access' );
        } );
    }
};
