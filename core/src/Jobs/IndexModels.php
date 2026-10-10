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


    /** @var bool TRUE to reindex only the source variants, defaults keep jobs queued by older versions working */
    public bool $sources = false;

    /** @var bool TRUE if the IDs are search keys (variant IDs for pages) */
    public bool $keys = false;


    /**
     * @param class-string<\Aimeos\Cms\Models\Base> $model
     * @param array<string> $ids
     * @param string $tenant Tenant ID
     * @param bool $sources TRUE to reindex only the source variants of the pages
     * @param bool $keys TRUE if the IDs are search keys (variant IDs for pages)
     */
    public function __construct( public string $model, public array $ids, public string $tenant, bool $sources = false,
        bool $keys = false )
    {
        $this->sources = $sources;
        $this->keys = $keys;
        $this->configureJob();
    }


    public function handle(): void
    {
        Tenancy::run( $this->tenant, fn() => Scout::sync( $this->model, $this->ids, $this->sources, $this->keys ) );
    }
}
