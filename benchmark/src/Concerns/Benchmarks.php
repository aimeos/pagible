<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Nestedset\NestedSet;
use Database\Seeders\BenchmarkSeeder;
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

        $run = function() use ( $fn, $readOnly, $conn ) {

            if( $readOnly )
            {
                $start = hrtime( true );
                $fn();
                $elapsed = hrtime( true ) - $start;
            }
            else
            {
                DB::connection( $conn )->beginTransaction();

                try {
                    $start = hrtime( true );
                    $fn();
                    $elapsed = hrtime( true ) - $start;
                } finally {
                    DB::connection( $conn )->rollBack();
                }
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
        $peaks = [];

        if( $verbose ) {
            DB::connection( $conn )->enableQueryLog();
        }

        gc_disable();

        for( $i = 0; $i < $tries; $i++ )
        {
            gc_collect_cycles();
            memory_reset_peak_usage();

            $before = memory_get_peak_usage();
            $durations[] = $execute();
            $peaks[] = memory_get_peak_usage() - $before;

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

        gc_enable();

        if( $verbose ) {
            DB::connection( $conn )->disableQueryLog();
        }

        $stats = $this->stats( $durations, $peaks );

        $this->line( sprintf(
            ' %-18s %9s %9s %9s %9s %9s %9s',
            $name,
            $this->format( $stats['min'] ),
            $this->format( $stats['max'] ),
            $this->format( $stats['avg'] ),
            $this->format( $stats['p95'] ),
            $this->format( $stats['p99'] ),
            $this->formatMem( $stats['peak'] ),
        ) );

        if( $verbose )
        {
            foreach( $queryTimes as $sql => $entry )
            {
                $type = strtoupper( strtok( ltrim( $sql ), ' ' ) ?: '' );
                $qStats = $this->stats( $entry['times'] );
                $this->line( sprintf(
                    '   %-16s %9s %9s %9s %9s %9s',
                    $type,
                    $this->format( $qStats['min'] ),
                    $this->format( $qStats['max'] ),
                    $this->format( $qStats['avg'] ),
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
     * Add an unpublished draft copying the latest version of the item.
     *
     * @param Page|Element|File $item Item to add the draft to
     */
    protected function draft( Page|Element|File $item ): void
    {
        $version = $item->versions()->forceCreate( [
            'lang' => 'en',
            'data' => (array) $item->latest?->data,
            'aux' => (array) $item->latest?->aux,
            'published' => false,
            'editor' => 'benchmark',
        ] );
        $item instanceof Page
            ? Page::withoutSyncingToSearch( fn() => \Aimeos\Cms\Resource::updatePage( $item, ['latest_id' => $version->id] ) )
            : $item->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $item->setRelation( 'latest', $version );
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
                $results = DB::connection( $conn )->select("
                        SELECT TOP 1 CAST(qp.query_plan AS NVARCHAR(MAX)) AS query_plan
                        FROM sys.dm_exec_query_stats AS qs
                        CROSS APPLY sys.dm_exec_sql_text(qs.sql_handle) AS st
                        CROSS APPLY sys.dm_exec_query_plan(qs.plan_handle) AS qp
                        WHERE st.text LIKE ? AND st.text NOT LIKE '%dm_exec%'
                        ORDER BY qs.last_execution_time DESC
                    ",
                    ['%' . substr( $sql, 0, 6 ) . '%']
                );

                return $this->xml2plan( $results[0]->query_plan ?? '' );
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
     * Load the benchmark items and add unpublished drafts for the publish benchmarks.
     *
     * Each benchmark iteration is rolled back, so the same items can be reused.
     *
     * @param string $domain Domain name
     * @param bool $all TRUE to add drafts for the element and file too, FALSE for the page only
     * @return array{root: Page, page: Page, parent: Page, element: Element, file: File, trashed: array{page: Page, element: Element, file: File}}
     */
    protected function fixtures( string $domain, bool $all = false ): array
    {
        $root = Page::where( 'tag', 'root' )->where( 'domain', $domain )->firstOrFail();

        // pages with translations, so the page operations include their language variants
        $pages = Page::where( 'tag', '!=', 'root' )->whereIn( 'id', PageVariant::where( 'lang', BenchmarkSeeder::TRANSLATION )->select( 'page_id' ) );
        $pages = $pages->exists() ? $pages : Page::where( 'tag', '!=', 'root' );

        $count = $pages->count();
        $page = $pages->orderBy( NestedSet::LFT )->skip( (int) floor( $count / 2 ) )->firstOrFail();

        $parent = Page::where( NestedSet::DEPTH, 1 )
            ->whereNotIn( 'id', $page->ancestors()->get()->pluck( 'id' ) )->firstOrFail();

        $element = Element::where( 'editor', 'benchmark' )->firstOrFail();
        $file = File::where( 'editor', 'benchmark' )->firstOrFail();

        foreach( $all ? [$page, $element, $file] : [$page] as $item )
        {
            $this->draft( $item );
        }

        // Pre-seeded soft-deleted items for the restore benchmarks
        $trashed = [
            'page' => Page::onlyTrashed()->firstOrFail(),
            'element' => Element::onlyTrashed()->where( 'editor', 'benchmark' )->firstOrFail(),
            'file' => File::onlyTrashed()->where( 'editor', 'benchmark' )->firstOrFail(),
        ];

        return compact( 'root', 'page', 'parent', 'element', 'file', 'trashed' );
    }


    /**
     * Compute min/max/avg/p90/p95/p99 from nanosecond durations.
     *
     * @param array<int, int|float> $durations Durations in nanoseconds
     * @param array<int, int|float> $peaks Peak memory usages in bytes
     * @return array<string, float> Stats in nanoseconds
     */
    protected function stats( array $durations, array $peaks = [] ): array
    {
        sort( $durations );
        $count = count( $durations );

        return [
            'min' => $durations[0],
            'max' => $durations[$count - 1],
            'avg' => array_sum( $durations ) / $count,
            'p95' => $durations[(int) ceil( $count * 0.95 ) - 1],
            'p99' => $durations[(int) ceil( $count * 0.99 ) - 1],
            'peak' => $peaks ? max( $peaks ) : 0,
        ];
    }


    /**
     * Format bytes to human-readable string.
     *
     * @param int|float $num Bytes
     * @return string Formatted string
     */
    protected function formatMem( int|float $num ): string
    {
        return number_format( $num / 1024, 1, '.', '' ) . 'KB';
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
        return number_format( $ms, 2, '.', '' ) . 'ms';
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

        $user->setAttribute( 'tenant_id', \Aimeos\Cms\Tenancy::value() );

        return $user;
    }


    /**
     * Run the closure as logged in benchmark user inside a rolled back transaction.
     *
     * @param Closure $fn Closure receiving the benchmark user
     */
    protected function sandbox( Closure $fn ): void
    {
        $conn = config( 'cms.db', 'sqlite' );

        DB::connection( $conn )->beginTransaction();

        try
        {
            $user = $this->user();
            Auth::login( $user );

            $fn( $user );
        }
        finally
        {
            Auth::logout();
            Auth::guard()->forgetUser();
            DB::connection( $conn )->rollBack();
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
            'Benchmark', 'Min', 'Max', 'Avg', 'P95', 'P99', 'Peak'
        ) );
        $this->line( ' ' . str_repeat( "\u{2500}", 78 ) );
    }


    /**
     * Validate common options, set up the tenant and abort if invalid.
     *
     * @param string $tenant Tenant ID
     * @param int $tries Number of iterations per benchmark
     * @param bool $force TRUE to run in production
     * @param bool $seeded TRUE to require existing benchmark data
     * @return bool True if validation passed
     */
    protected function checks( string $tenant, int $tries, bool $force = false, bool $seeded = true ): bool
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

        \Aimeos\Cms\Tenancy::set( $tenant );

        if( $seeded && !Page::where( 'editor', 'benchmark' )->exists() )
        {
            $this->error( 'No benchmark data found. Run `php artisan cms:benchmark --seed` first.' );
            return false;
        }

        return true;
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
            $node->registerXPathNamespace('qp', 'http://schemas.microsoft.com/sqlserver/2004/07/showplan');
            $depth = count($node->xpath('ancestor::qp:RelOp') ?: []);
            $indent = str_repeat('  ', $depth);

            $raw[] = $indent . (string) $node['PhysicalOp']
                . ' / ' . (string) $node['LogicalOp']
                . ' (cost: ' . round((float) $node['EstimatedTotalSubtreeCost'], 4) . ')';

            // Pull index/table info from child Object elements
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

        // Collapse consecutive repeated line groups (single or multi-line patterns)
        $lines = [];
        $total = count($raw);
        $i = 0;

        while ($i < $total)
        {
            $bestSize = 1;
            $bestCount = 1;
            $maxGroup = min(5, intdiv($total - $i, 2));

            for ($size = 1; $size <= $maxGroup; $size++)
            {
                $count = 1;

                while ($i + ($count + 1) * $size <= $total)
                {
                    $match = true;

                    for ($k = 0; $k < $size; $k++) {
                        if ($raw[$i + $k] !== $raw[$i + $count * $size + $k]) {
                            $match = false;
                            break;
                        }
                    }

                    if (!$match) {
                        break;
                    }

                    $count++;
                }

                if ($count > 1 && $size * $count > $bestSize * $bestCount) {
                    $bestSize = $size;
                    $bestCount = $count;
                }
            }

            for ($k = 0; $k < $bestSize; $k++) {
                $line = $raw[$i + $k];
                $lines[] = ($bestCount > 1 && $k === $bestSize - 1)
                    ? $line . ' [x' . $bestCount . ']'
                    : $line;
            }

            $i += $bestSize * $bestCount;
        }

        return $lines;
    }
}
