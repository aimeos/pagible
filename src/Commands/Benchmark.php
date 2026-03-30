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
        {--seed : Seed benchmark data before running benchmarks}
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
            return self::FAILURE;
        }

        $tenant = (string) $this->option( 'tenant' ); // @phpstan-ignore-line argument.type

        if( empty( $tenant ) )
        {
            $this->error( 'The --tenant option must not be empty.' );
            return self::FAILURE;
        }

        // Set up tenancy
        \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
            return $tenant;
        };

        $conn = config( 'cms.db', 'sqlite' );
        $domain = (string) $this->option( 'domain' ) ?: ''; // @phpstan-ignore-line argument.type

        // Unseed mode
        if( $this->option( 'unseed' ) )
        {
            $this->info( 'Removing benchmark data...' );
            $this->unseed( $conn, $tenant, $domain );
            $this->info( 'Done!' );
            return self::SUCCESS;
        }

        // Seed phase
        if( $this->option( 'seed' ) )
        {
            $editor = (string) $this->option( 'editor' ) ?: ''; // @phpstan-ignore-line argument.type
            $lang = (string) $this->option( 'lang' ) ?: ''; // @phpstan-ignore-line argument.type
            $pages = (int) $this->option( 'pages' );
            $chunk = (int) $this->option( 'chunk' );

            $this->info( "Seeding {$pages} benchmark pages for language: {$lang}" );

            $fileCount = max( 2, intdiv( $pages, 10 ) );
            $totalRows = $pages + $fileCount + 1 + ( $pages + $fileCount ) + ( $pages * 4 );
            $bar = $this->output->createProgressBar( $totalRows );
            $bar->setFormat( ' [%bar%] %percent:3s%% %elapsed%' );

            $seeder = new BenchmarkSeeder();
            $seeder->run( $lang, $domain, $editor, $pages, $chunk, function( int $count ) use ( $bar ) {
                $bar->advance( $count );
            } );

            $bar->finish();
            $this->newLine();
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

        if( $this->option( 'seed' ) ) {
            $sharedOptions['--seed'] = true;
        }

        foreach( $commands as $command )
        {
            $this->comment( sprintf( '  Running %s', $command ) );
            $this->call( $command, $sharedOptions );
        }

        $this->info( 'All benchmarks complete.' );

        return self::SUCCESS;
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

        $pageIds = DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );
        $elementIds = DB::connection( $conn )->table( 'cms_elements' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );
        $versionIds = DB::connection( $conn )->table( 'cms_versions' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );
        $fileIds = DB::connection( $conn )->table( 'cms_files' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );

        // Delete pivot tables (no tenant_id column)
        foreach( $pageIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_page_file' )->whereIn( 'page_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_page_element' )->whereIn( 'page_id', $chunk )->delete();
        }

        foreach( $versionIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_version_file' )->whereIn( 'version_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_version_element' )->whereIn( 'version_id', $chunk )->delete();
        }

        // Delete search index (no editor column)
        $allIds = $pageIds->merge( $elementIds )->merge( $fileIds );

        foreach( $allIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_index' )
                ->whereIn( 'indexable_id', $chunk )
                ->delete();
        }

        // Delete main tables
        $tables = ['cms_versions', 'cms_elements', 'cms_files', 'cms_pages'];

        foreach( $tables as $table )
        {
            DB::connection( $conn )->table( $table )
                ->where( 'tenant_id', $tenant )
                ->where( 'editor', 'benchmark' )
                ->delete();
        }
    }
}
