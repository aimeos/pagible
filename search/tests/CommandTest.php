<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Database\Seeders\CmsSeeder;
use Illuminate\Support\Facades\DB;


class SearchCommandTest extends SearchTestAbstract
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function testIndex(): void
    {
        $this->seed( CmsSeeder::class );

        $this->artisan('cms:index')->assertExitCode( 0 );

        $this->assertEquals( 18, DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_index' )->count() );
    }
}
