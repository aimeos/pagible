<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Closure;


trait Benchmarks
{
    /**
     * Run a benchmark, printing stats when done.
     *
     * @param string $name Benchmark name
     * @param Closure $fn Benchmark closure
     * @param bool $readOnly If true, skip transaction wrapping
     * @param bool $searchSync If true, keep search syncing enabled
     */
    protected function benchmark( string $name, Closure $fn, bool $readOnly = false, bool $searchSync = false ): void
    {
        $tries = (int) $this->option( 'tries' );
        $conn = config( 'cms.db', 'sqlite' );

        DB::disableQueryLog();
        gc_disable();

        $run = function() use ( $fn, $readOnly, $conn ) {
            if( $readOnly )
            {
                $start = hrtime( true );
                $fn();
                return hrtime( true ) - $start;
            }

            DB::connection( $conn )->beginTransaction();

            try {
                $start = hrtime( true );
                $fn();
                $elapsed = hrtime( true ) - $start;
            } finally {
                DB::connection( $conn )->rollBack();
            }

            return $elapsed;
        };

        $execute = function() use ( $run, $searchSync ) {
            if( $searchSync ) {
                return $run();
            }

            $result = 0;

            Page::withoutSyncingToSearch( function() use ( &$result, $run ) {
                Element::withoutSyncingToSearch( function() use ( &$result, $run ) {
                    File::withoutSyncingToSearch( function() use ( &$result, $run ) {
                        $result = $run();
                    } );
                } );
            } );

            return $result;
        };

        // Warmup iteration
        $execute();

        $durations = [];

        for( $i = 0; $i < $tries; $i++ ) {
            $durations[] = $execute();
        }

        gc_enable();
        gc_collect_cycles();

        $stats = $this->stats( $durations );
        $this->line( sprintf(
            ' %-18s %9s %9s %9s %9s %9s %9s',
            $name,
            $this->format( $stats['min'] ),
            $this->format( $stats['max'] ),
            $this->format( $stats['avg'] ),
            $this->format( $stats['p90'] ),
            $this->format( $stats['p95'] ),
            $this->format( $stats['p99'] ),
        ) );
    }


    /**
     * Compute min/max/avg/p90/p95/p99 from nanosecond durations.
     *
     * @param array<int, int|float> $durations Durations in nanoseconds
     * @return array<string, float> Stats in nanoseconds
     */
    protected function stats( array $durations ): array
    {
        sort( $durations );
        $count = count( $durations );

        return [
            'min' => $durations[0],
            'max' => $durations[$count - 1],
            'avg' => array_sum( $durations ) / $count,
            'p90' => $durations[(int) ceil( $count * 0.90 ) - 1],
            'p95' => $durations[(int) ceil( $count * 0.95 ) - 1],
            'p99' => $durations[(int) ceil( $count * 0.99 ) - 1],
        ];
    }


    /**
     * Format nanoseconds to human-readable string.
     *
     * @param int|float $ns Nanoseconds
     * @return string Formatted string
     */
    protected function format( int|float $ns ): string
    {
        $ms = $ns / 1_000_000;
        return number_format( $ms, 2 ) . 'ms';
    }


    /**
     * Set up tenancy from the --tenant option.
     */
    protected function tenant(): void
    {
        $tenant = $this->option( 'tenant' );

        \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
            return $tenant;
        };
    }


    /**
     * Run a seeder class.
     *
     * @param string $seederClass Fully qualified seeder class name
     * @param mixed ...$args Arguments to pass to run()
     */
    protected function seed( string $seederClass, ...$args ): void
    {
        $seeder = new $seederClass;

        if( $seeder instanceof \Illuminate\Database\Seeder ) {
            $seeder( ...$args );
        }
    }


    /**
     * Print the benchmark table header.
     */
    protected function header(): void
    {
        $this->line( '' );
        $this->line( sprintf(
            ' %-18s %9s %9s %9s %9s %9s %9s',
            'Benchmark', 'Min', 'Max', 'Avg', 'P90', 'P95', 'P99'
        ) );
        $this->line( ' ' . str_repeat( "\u{2500}", 78 ) );
    }


    /**
     * Validate common options and abort if invalid.
     *
     * @return bool True if validation passed
     */
    protected function validateOptions(): bool
    {
        if( empty( $this->option( 'tenant' ) ) )
        {
            $this->error( 'The --tenant option must not be empty.' );
            return false;
        }

        if( app()->isProduction() && !$this->option( 'force' ) )
        {
            $this->error( 'Use --force to run in production.' );
            return false;
        }

        return true;
    }


    /**
     * Check if benchmark data exists for the current tenant/domain/lang.
     *
     * @return bool True if data exists
     */
    protected function hasSeededData(): bool
    {
        $this->tenant();
        return Page::where( 'editor', 'benchmark' )->exists();
    }
}
