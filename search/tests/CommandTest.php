<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;


class SearchCommandTest extends SearchTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected $seeder = TestSeeder::class;
    protected $connectionsToTruncate = ['testing'];


    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
    }


    public function testIndex(): void
    {
        // Clear orphaned FTS data from other test classes (FTS5 virtual tables
        // are not truncated by DatabaseTruncation)
        \Aimeos\Cms\DB::connection()->table( 'cms_index' )->delete();

        $this->artisan('cms:index')->assertExitCode( 0 );

        $this->assertEquals( 20, \Aimeos\Cms\DB::connection()->table( 'cms_index' )->count() );
    }
}
