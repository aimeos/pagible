<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Scout\Traits\ConfiguresJobOptions;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Tenancy;


class IndexModels implements ShouldQueue
{
    use ConfiguresJobOptions;
    use Queueable;
    use SerializesModels;


    /**
     * @param class-string<\Aimeos\Cms\Models\Base> $model
     * @param array<string> $ids
     * @param string $tenant Tenant ID
     * @param bool $sources TRUE to reindex only the source variants of the pages
     */
    public function __construct( public string $model, public array $ids, public string $tenant, public bool $sources = false )
    {
        $this->configureJob();
    }


    public function handle(): void
    {
        Tenancy::run( $this->tenant, fn() => $this->sources
            ? Scout::syncSources( $this->ids )
            : Scout::sync( $this->model, $this->ids ) );
    }
}
