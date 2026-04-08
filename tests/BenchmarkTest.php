<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Output\ConsoleOutput;


#[Group( 'benchmark' )]
class BenchmarkTest extends CmsTestAbstract
{
    protected function defineDatabaseMigrations()
    {
        parent::defineDatabaseMigrations();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        \Aimeos\Cms\Tenancy::$callback = function() {
            return 'benchmark';
        };
    }


    protected function getPackageProviders( $app )
    {
        return [
            'Aimeos\Cms\CoreServiceProvider',
            'Aimeos\Cms\GraphqlServiceProvider',
            'Aimeos\Cms\JsonapiServiceProvider',
            'Aimeos\Cms\McpServiceProvider',
            'Aimeos\Cms\SearchServiceProvider',
            'Aimeos\Cms\ThemeServiceProvider',
            'Aimeos\Cms\ServiceProvider',
            'Aimeos\Nestedset\NestedSetServiceProvider',
        ];
    }


    public function testBenchmark(): void
    {
        $output = new ConsoleOutput();
        $chunk = DB::connection( config( 'cms.db', 'sqlite' ) )->getDriverName() === 'sqlsrv' ? 75 : 500;

        $seed = Artisan::call( 'cms:benchmark', [
            '--seed' => true,
            '--domain' => 'benchmark',
            '--pages' => 10000,
            '--chunk' => $chunk,
            '--force' => true,
        ], $output );

        $this->assertSame( 0, $seed, 'Seeding failed: ' . Artisan::output() );

        $run = Artisan::call( 'cms:benchmark:graphql', [
            '--domain' => 'benchmark',
            '--tries' => 10,
            '--chunk' => $chunk,
            '--force' => true,
            '-vvv' => true,
        ], $output );

        $this->assertSame( 0, $run, 'Benchmark run failed: ' . Artisan::output() );

        $unseed = Artisan::call( 'cms:benchmark', [
            '--unseed' => true,
            '--domain' => 'benchmark',
            '--force' => true,
        ], $output );

        $this->assertSame( 0, $unseed, 'Unseeding failed: ' . Artisan::output() );
    }
}
