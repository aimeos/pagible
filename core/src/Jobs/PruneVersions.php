<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\Base;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;


class PruneVersions implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;


    /**
     * @param class-string<Base> $model Model class
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
     */
    public function __construct( public string $model, public string $tenant, public array $ids )
    {
        sort( $this->ids );
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
