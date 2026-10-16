<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class PruneVersions implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use RoutesQueue;


    /**
     * @param class-string<Base> $model Model class
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
     */
    public function __construct( public string $model, public string $tenant, public array $ids )
    {
        sort( $this->ids );
        $this->routeQueue();
    }


    /**
     * Queues version pruning in bounded batches after the current transaction commits.
     *
     * @param class-string<Base> $model Model class
     * @param array<string|null> $ids Model IDs, NULL values are skipped
     */
    public static function dispatchChunked( string $model, array $ids ) : void
    {
        foreach( array_chunk( array_unique( array_filter( $ids, is_string(...) ) ), Base::CHUNK ) as $chunk ) {
            static::dispatch( $model, Tenancy::value(), $chunk )->afterCommit();
        }
    }


    public function handle(): void
    {
        $this->model::pruneVersions( $this->tenant, $this->ids );
    }


    public function uniqueId(): string
    {
        return hash( 'sha256', $this->model . ':' . $this->tenant . ':' . implode( ',', $this->ids ) );
    }
}
