<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Output\ConsoleOutput;


#[Group( 'benchmark' )]
class BenchmarkTest extends CmsTestAbstract
{
    use CmsWithMigrations {
        defineDatabaseMigrations as defineCmsDatabaseMigrations;
    }


    protected function defineDatabaseMigrations()
    {
        $this->defineCmsDatabaseMigrations();
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

        $this->assertFalse( Access::isAvailable() );
        $this->assertFalse( config( 'cms.multidomain' ) );

        $seed = Artisan::call( 'cms:benchmark', [
            '--seed' => true,
            '--domain' => 'benchmark',
            '--pages' => 10000,
            '--chunk' => $chunk,
            '--force' => true,
        ], $output );

        $this->assertSame( 0, $seed, 'Seeding failed: ' . Artisan::output() );

        $run = Artisan::call( 'cms:benchmark', [
            '--domain' => 'benchmark',
            '--tries' => 10,
            '--chunk' => $chunk,
            '--force' => true,
            '-v' => true,
        ], $output );

        $this->assertSame( 0, $run, 'Benchmark run failed: ' . Artisan::output() );
        $this->assertFalse( Access::isAvailable() );
        $this->assertFalse( config( 'cms.multidomain' ) );

        Access::availableUsing( fn() => ['custom.frontend'] );
        DB::table( 'users' )->where( 'email', 'benchmark@example.com' )->delete();

        $theme = Artisan::call( 'cms:benchmark:theme', [
            '--domain' => 'benchmark',
            '--tries' => 1,
            '--force' => true,
        ], $output );

        $this->assertSame( 0, $theme, 'Theme benchmark failed: ' . Artisan::output() );
        $this->assertSame( ['custom.frontend'], app( Access::class )->all() );
        $this->assertFalse( config( 'cms.multidomain' ) );

        Access::availableUsing( null );

        $unseed = Artisan::call( 'cms:benchmark', [
            '--unseed' => true,
            '--domain' => 'benchmark',
            '--force' => true,
        ], $output );

        $this->assertSame( 0, $unseed, 'Unseeding failed: ' . Artisan::output() );
    }
}
