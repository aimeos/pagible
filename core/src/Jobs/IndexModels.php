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
use Aimeos\Cms\Models\Base;


class IndexModels implements ShouldQueue
{
    use ConfiguresJobOptions;
    use Queueable;
    use SerializesModels;


    /** @var bool TRUE if the IDs are search keys (variant IDs for pages), the default keeps jobs queued by older versions working */
    public bool $keys = false;


    /**
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $tenant Tenant ID
     * @param bool $keys TRUE if the IDs are search keys (variant IDs for pages)
     */
    public function __construct( public string $model, public array $ids, public string $tenant, bool $keys = false )
    {
        $this->keys = $keys;
        $this->configureJob();
    }


    public function handle(): void
    {
        Tenancy::run( $this->tenant, fn() => Scout::sync( $this->model, $this->ids, $this->keys ) );
    }
}
