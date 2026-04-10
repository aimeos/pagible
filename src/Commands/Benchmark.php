<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Database\Seeders\BenchmarkSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class Benchmark extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:benchmark
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--pages=10000 : Total number of pages to create}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=50 : Rows per bulk insert batch}
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

        $tenant = $this->option( 'tenant' );

        if( empty( $tenant ) || !is_string( $tenant ) )
        {
            $this->error( 'The --tenant option must be a non-empty string.' );
            return self::FAILURE;
        }

        // Set up tenancy
        \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
            return $tenant;
        };

        $domain = $this->option( 'domain' );

        if( empty( $domain ) || !is_string( $domain ) )
        {
            $this->error( 'The --domain option must be a non-empty string.' );
            return self::FAILURE;
        }

        // Unseed mode
        if( $this->option( 'unseed' ) )
        {
            $commands = collect( Artisan::all() )
                ->filter( fn( $cmd, $name ) => str_starts_with( $name, 'cms:benchmark:' ) )
                ->keys()
                ->sort();

            foreach( $commands as $command )
            {
                $this->call( $command, [
                    '--unseed' => true,
                    '--tenant' => $tenant,
                    '--domain' => $domain,
                    '--force' => true,
                ] );
            }

            return self::SUCCESS;
        }

        // Seed phase
        if( $this->option( 'seed' ) )
        {
            return $this->seed( $domain );
        }

        // Discover and run sub-package benchmark commands
        $commands = collect( Artisan::all() )
            ->filter( fn( $cmd, $name ) => str_starts_with( $name, 'cms:benchmark:' ) )
            ->keys()
            ->sort();

        $sharedOptions = [
            '--tenant' => $tenant,
            '--domain' => $domain,
            '--pages' => $this->option( 'pages' ),
            '--tries' => $this->option( 'tries' ),
            '--chunk' => $this->option( 'chunk' ),
            '--force' => true,
        ];

        foreach( $commands as $command )
        {
            $this->comment( sprintf( '  Running %s', $command ) );
            $this->call( $command, $sharedOptions );
        }

        $this->info( 'All benchmarks complete.' );

        return self::SUCCESS;
    }


    /**
     * Seed benchmark data.
     */
    protected function seed( string $domain ): int
    {
        $pages = (int) $this->option( 'pages' );
        $chunk = (int) $this->option( 'chunk' );

        $this->info( "Seeding {$pages} benchmark pages" );

        $fileCount = max( 2, intdiv( $pages, 10 ) );
        $totalRows = $pages + $fileCount + 1 + ( $pages + $fileCount ) + ( $pages * 4 );
        $bar = $this->output->createProgressBar( $totalRows );
        $bar->setFormat( ' [%bar%] %percent:3s%% %elapsed%' );

        $seeder = new BenchmarkSeeder();
        $seeder->run( $domain, 'benchmark', $pages, $chunk, function( int $count ) use ( $bar ) {
            $bar->advance( $count );
        } );

        $bar->finish();
        $this->newLine();
        $this->info( 'Seeding complete.' );

        return self::SUCCESS;
    }

}
