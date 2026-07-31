<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierSetup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class CashierSetupTest extends CashierTestAbstract
{
    public function testChecksPagibleAccessTraitOnAuthenticationModel(): void
    {
        $check = collect( app( CashierSetup::class )->checks() )->firstWhere( 'name', 'CashierAccess trait' );

        $this->assertIsArray( $check );
        $this->assertTrue( $check['ok'] );
        $this->assertStringContainsString( 'Concerns\\CashierAccess', $check['message'] );
    }


    public function testConflictDetectsAnUnownedAccessColumn(): void
    {
        Schema::create( 'users', function( Blueprint $table ) {
            $table->id();
            $table->json( 'access' )->nullable();
        } );
        Schema::create( 'migrations', function( Blueprint $table ) {
            $table->id();
            $table->string( 'migration' );
            $table->integer( 'batch' );
        } );

        $setup = app( CashierSetup::class );

        $this->assertStringContainsString( 'not owned', (string) $setup->conflict() );

        DB::table( 'migrations' )->insert( [
            'migration' => '2026_07_26_000000_add_users_access',
            'batch' => 1,
        ] );

        $this->assertNull( $setup->conflict() );
    }
}
