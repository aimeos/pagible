<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\CheckSchema;
use Aimeos\Cms\Schema;


class CheckSchemaTest extends ThemeTestAbstract
{
    public function testErrors() : void
    {
        $dir = sys_get_temp_dir() . '/cms-check-' . uniqid();
        mkdir( $dir );
        file_put_contents( $dir . '/schema.json', json_encode( ['content' => ['promo' => ['fields' => [
            'action' => ['type' => 'hidden', 'value' => '\\App\\Missing'],
            'list' => ['type' => 'items', 'item' => ['flag' => ['type' => 'boolean']]],
            'mode' => ['type' => 'select'],
        ]]]] ) );

        try
        {
            Schema::register( $dir, 'broken' );

            $this->artisan( 'cms:schema:check', ['theme' => ['broken']] )
                ->expectsOutput( 'broken: "broken::promo.list.flag" has the unknown type "boolean"' )
                ->expectsOutput( 'broken: "broken::promo.mode" has no options' )
                ->expectsOutput( 'broken: "broken::promo" uses the action "\\App\\Missing" which doesn\'t exist' )
                ->expectsOutput( 'broken: "broken::promo" has no view' )
                ->assertExitCode( 1 );
        }
        finally
        {
            unlink( $dir . '/schema.json' );
            rmdir( $dir );
        }
    }


    public function testRegistered() : void
    {
        $this->artisan( 'cms:schema:check' )->expectsOutput( 'No errors found' )->assertExitCode( 0 );
    }


    public function testTypes() : void
    {
        $files = glob( dirname( __DIR__, 2 ) . '/admin/js/fields/*.vue' ) ?: [];
        $types = array_map( fn( $file ) => strtolower( basename( $file, '.vue' ) ), $files );

        if( empty( $types ) ) {
            $this->markTestSkipped( 'Admin sources not available' );
        }

        $this->assertEqualsCanonicalizing( $types, CheckSchema::TYPES );
    }
}
