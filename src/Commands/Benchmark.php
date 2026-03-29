<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Database\Seeders\BenchmarkSeeder;


class Benchmark extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:benchmark
        {--lang=en : Language code}
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--editor=benchmark : Editor name}
        {--pages=10000 : Total number of pages to create}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--seed-only : Only seed, skip benchmarks}
        {--test-only : Only run benchmarks, skip seeding}
        {--unseed : Remove all benchmark data and exit}
        {--force : Force the operation to run in production}';

    /**
     * Command description
     */
    protected $description = 'Seeds benchmark data and runs performance benchmarks across all CMS packages';


    /**
     * Execute command
     */
    public function handle(): int
    {
        if( app()->isProduction() && !$this->option( 'force' ) )
        {
            $this->error( 'Use --force to run in production.' );
            return 1;
        }

        $tenant = strval( $this->option( 'tenant' ) );

        if( empty( $tenant ) )
        {
            $this->error( 'The --tenant option must not be empty.' );
            return 1;
        }

        // Set up tenancy
        \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
            return $tenant;
        };

        $domain = strval( $this->option( 'domain' ) ?: '' );
        $conn = config( 'cms.db', 'sqlite' );

        // Unseed mode
        if( $this->option( 'unseed' ) )
        {
            $this->info( 'Removing benchmark data...' );
            $this->unseed( $conn, $tenant, $domain );
            $this->info( 'Done!' );
            return 0;
        }

        // Seed phase
        if( !$this->option( 'test-only' ) )
        {
            $lang = strval( $this->option( 'lang' ) ?: '' );
            $editor = strval( $this->option( 'editor' ) ?: '' );
            $pages = (int) $this->option( 'pages' );
            $chunk = (int) $this->option( 'chunk' );

            $this->info( "Seeding {$pages} benchmark pages for language: {$lang}" );

            $seeder = new BenchmarkSeeder();
            $seeder->run( $lang, $domain, $editor, $pages, $chunk );

            $this->info( 'Seeding complete.' );
        }

        // Discover and run sub-package benchmark commands
        $commands = collect( Artisan::all() )
            ->filter( fn( $cmd, $name ) => str_starts_with( $name, 'cms:benchmark:' ) )
            ->keys()
            ->sort();

        $sharedOptions = [
            '--tenant' => $tenant,
            '--domain' => $domain,
            '--lang' => $this->option( 'lang' ),
            '--pages' => $this->option( 'pages' ),
            '--tries' => $this->option( 'tries' ),
            '--chunk' => $this->option( 'chunk' ),
            '--force' => true,
        ];

        if( $this->option( 'seed-only' ) ) {
            $sharedOptions['--seed-only'] = true;
        }

        if( $this->option( 'test-only' ) ) {
            $sharedOptions['--test-only'] = true;
        }

        foreach( $commands as $command )
        {
            $this->comment( sprintf( '  Running %s ...', $command ) );
            $this->call( $command, $sharedOptions );
        }

        $this->info( 'All benchmarks complete.' );

        return 0;
    }


    /**
     * Remove all benchmark data for the tenant, respecting FK constraints.
     */
    protected function unseed( string $conn, string $tenant, string $domain ): void
    {
        // Clear cache for benchmark pages
        Page::where( 'editor', 'benchmark' )->each( function( $page ) {
            Cache::forget( Page::key( $page ) );
        } );

        // Break circular page↔version FK by clearing latest_id first
        DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', $tenant )
            ->where( 'editor', 'benchmark' )
            ->update( ['latest_id' => null] );

        // Delete in FK-safe order
        $tables = [
            'cms_index',
            'cms_page_file', 'cms_page_element',
            'cms_version_file', 'cms_version_element',
            'cms_versions', 'cms_elements', 'cms_files', 'cms_pages',
        ];

        foreach( $tables as $table )
        {
            DB::connection( $conn )->table( $table )
                ->where( 'tenant_id', $tenant )
                ->delete();
        }
    }
}
