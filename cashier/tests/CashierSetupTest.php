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
        $migration = '2026_07_26_000000_add_users_access';
        $users = Schema::hasTable( 'users' );
        $access = $users && Schema::hasColumn( 'users', 'access' );
        $migrations = Schema::hasTable( 'migrations' );
        $records = $migrations
            ? DB::table( 'migrations' )->where( 'migration', $migration )->get()->map( fn( object $row ) => (array) $row )->all()
            : [];

        try
        {
            if( !$users ) {
                Schema::create( 'users', function( Blueprint $table ) {
                    $table->id();
                    $table->json( 'access' )->nullable();
                } );
            } elseif( !$access ) {
                Schema::table( 'users', fn( Blueprint $table ) => $table->json( 'access' )->nullable() );
            }

            if( !$migrations ) {
                Schema::create( 'migrations', function( Blueprint $table ) {
                    $table->id();
                    $table->string( 'migration' );
                    $table->integer( 'batch' );
                } );
            }

            DB::table( 'migrations' )->where( 'migration', $migration )->delete();

            $setup = app( CashierSetup::class );

            $this->assertStringContainsString( 'not owned', (string) $setup->conflict() );

            DB::table( 'migrations' )->insert( ['migration' => $migration, 'batch' => 1] );

            $this->assertNull( $setup->conflict() );
        }
        finally
        {
            if( Schema::hasTable( 'migrations' ) ) {
                DB::table( 'migrations' )->where( 'migration', $migration )->delete();

                if( $records !== [] ) {
                    DB::table( 'migrations' )->insert( $records );
                }
            }

            if( !$migrations ) {
                Schema::dropIfExists( 'migrations' );
            }

            if( !$users ) {
                Schema::dropIfExists( 'users' );
            } elseif( !$access && Schema::hasColumn( 'users', 'access' ) ) {
                Schema::table( 'users', fn( Blueprint $table ) => $table->dropColumn( 'access' ) );
            }
        }
    }
}
