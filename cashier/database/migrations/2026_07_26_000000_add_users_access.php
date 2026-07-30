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
     * Adds the reserved payment-derived access projection to users.
     */
    public function up(): void
    {
        if( Schema::hasColumn( 'users', 'access' ) ) {
            return;
        }

        Schema::table( 'users', function( Blueprint $table ) {
            $table->json( 'access' )->nullable();
        } );
    }


    /**
     * Removes the payment-derived access projection from users.
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
