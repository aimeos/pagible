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
        // Clear orphaned FTS data from other test classes (FTS5 virtual tables
        // are not truncated by DatabaseTruncation in other test classes)
        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_index' )->delete();

        $this->seed( CmsSeeder::class );

        $this->artisan('cms:index')->assertExitCode( 0 );

        $this->assertEquals( 20, DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_index' )->count() );
    }
}
