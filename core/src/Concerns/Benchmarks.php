<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    protected function benchmark( string $name, Closure $fn, bool $readOnly = false, int $tries = 100, bool $searchSync = false ): void
    {
        $conn = config( 'cms.db', 'sqlite' );

        DB::connection( $conn )->disableQueryLog();
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

        $verbose = $this->output->isVerbose();
        $queryTimes = [];
        $durations = [];

        if( $verbose ) {
            DB::connection( $conn )->enableQueryLog();
        }

        for( $i = 0; $i < $tries; $i++ )
        {
            $durations[] = $execute();

            if( $verbose )
            {
                $log = DB::connection( $conn )->getQueryLog();
                DB::connection( $conn )->flushQueryLog();

                foreach( $log as $q ) {
                    $queryTimes[$q['query']]['times'][] = $q['time'] * 1_000_000;
                    $queryTimes[$q['query']]['bindings'] = $q['bindings'];
                }
            }
        }

        if( $verbose ) {
            DB::connection( $conn )->disableQueryLog();
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

        if( $verbose )
        {
            foreach( $queryTimes as $sql => $entry )
            {
                $type = strtoupper( strtok( ltrim( $sql ), ' ' ) ?: '' );
                $qStats = $this->stats( $entry['times'] );
                $this->line( sprintf(
                    '   %-16s %9s %9s %9s %9s %9s %9s',
                    $type,
                    $this->format( $qStats['min'] ),
                    $this->format( $qStats['max'] ),
                    $this->format( $qStats['avg'] ),
                    $this->format( $qStats['p90'] ),
                    $this->format( $qStats['p95'] ),
                    $this->format( $qStats['p99'] ),
                ) );

                if( $this->output->isVeryVerbose() ) {
                    $this->line( '     ' . $sql );
                }

                if( $this->output->isDebug() )
                {
                    foreach( $this->explain( $sql, $entry['bindings'], $conn ) as $line ) {
                        $this->line( '       ' . $line );
                    }
                }
            }

            $this->line( '' );
        }
    }


    /**
     * Get the query execution plan for a SQL statement.
     *
     * @param string $sql SQL query
     * @param array<mixed> $bindings Query bindings
     * @param string $conn Connection name
     * @return array<int, string> Plan lines
     */
    protected function explain( string $sql, array $bindings, string $conn ): array
    {
        $driver = DB::connection( $conn )->getDriverName();

        try
        {
            if( $driver === 'sqlsrv' )
            {
                $pdo = DB::connection( $conn )->getPdo();
                $pdo->exec( 'SET SHOWPLAN_XML ON' );

                try {
                    $resolved = $sql;
                    foreach( $bindings as $value ) {
                        $resolved = preg_replace( '/\?/', $pdo->quote( (string) $value ), $resolved, 1 );
                    }

                    $stmt = $pdo->query( $resolved );

                    $xml = '';
                    do {
                        if( $stmt->columnCount() > 0 && ( $row = $stmt->fetch( \PDO::FETCH_NUM ) )) {
                            $xml = $row[0];
                        }
                    } while( $stmt->nextRowset() );

                    $stmt->closeCursor();

                    return $this->xml2plan( $xml );
                } finally {
                    $pdo->exec( 'SET SHOWPLAN_XML OFF' );
                }
            }

            $prefix = $driver === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
            $rows = DB::connection( $conn )->select( $prefix . $sql, $bindings );

            $lines = [];

            foreach( $rows as $row )
            {
                $row = (array) $row;

                if( $driver === 'sqlite' ) {
                    $lines[] = ( $row['detail'] ?? implode( ' | ', $row ) );
                } else {
                    $lines[] = implode( ' | ', $row );
                }
            }

            return $lines;
        }
        catch( \Throwable $e )
        {
            return ['EXPLAIN failed: ' . $e->getMessage(), $e->getTraceAsString()];
        }
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
    protected function tenant( string $tenant ): void
    {
        \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
            return $tenant;
        };
    }


    /**
     * Create a benchmark user with full CMS permissions.
     *
     * @return \Illuminate\Foundation\Auth\User
     */
    protected function user(): \Illuminate\Foundation\Auth\User
    {
        $userClass = config( 'auth.providers.users.model', 'App\\Models\\User' );
        $user = new $userClass();

        if( !$user instanceof \Illuminate\Foundation\Auth\User ) {
            throw new \RuntimeException( 'User model must extend Illuminate\Foundation\Auth\User' );
        }

        $user->forceFill( [
            'name' => 'Benchmark User',
            'email' => 'benchmark@example.com',
            'password' => bcrypt( Str::random( 64 ) ),
            'cmsperms' => ['*'],
        ] )->save();

        return $user;
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
    protected function checks( string $tenant, int $tries, bool $force = false ): bool
    {
        if( empty( $tenant ) )
        {
            $this->error( 'The --tenant option must not be empty.' );
            return false;
        }

        if( $tries <= 0 )
        {
            $this->error( 'The --tries option must be greater than 0.' );
            return false;
        }

        if( app()->isProduction() && !$force )
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
        return Page::where( 'editor', 'benchmark' )->exists();
    }


    /**
     * @return array<int, string>
     */
    protected function xml2plan( string $xml ) : array
    {
        $doc = simplexml_load_string($xml);

        if( $doc === false ) {
            return [];
        }

        $doc->registerXPathNamespace('qp', 'http://schemas.microsoft.com/sqlserver/2004/07/showplan');

        $nodes = $doc->xpath('//qp:RelOp') ?: [];
        $raw = [];

        foreach ($nodes as $node) {
            $indent = str_repeat('  ', (int) $node['NodeId']);

            $raw[] = $indent . (string) $node['PhysicalOp']
                . ' / ' . (string) $node['LogicalOp']
                . ' (cost: ' . round((float) $node['EstimatedTotalSubtreeCost'], 4) . ')';

            // Pull index/table info from child Object elements
            $node->registerXPathNamespace('qp', 'http://schemas.microsoft.com/sqlserver/2004/07/showplan');
            $objects = $node->xpath('*/qp:Object') ?: [];

            foreach ($objects as $obj) {
                $parts = array_filter([
                    (string) $obj['Table'],
                    (string) $obj['Index'],
                    (string) $obj['Alias'] ? 'AS ' . (string) $obj['Alias'] : null,
                ]);
                if ($parts) {
                    $raw[] = $indent . '  → ' . implode(' ', $parts);
                }
            }
        }

        // Collapse consecutive duplicate lines
        $lines = [];
        $prev = null;
        $count = 0;

        foreach ($raw as $line) {
            if ($line === $prev) {
                $count++;
            } else {
                if ($prev !== null) {
                    $lines[] = $count > 1 ? $prev . ' [x' . $count . ']' : $prev;
                }
                $prev = $line;
                $count = 1;
            }
        }

        if ($prev !== null) {
            $lines[] = $count > 1 ? $prev . ' [x' . $count . ']' : $prev;
        }

        return $lines;
    }
}