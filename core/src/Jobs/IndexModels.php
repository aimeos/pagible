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
     */
    public function __construct( public string $model, public array $ids, public string $tenant )
    {
        $this->configureJob();
    }


    public function handle(): void
    {
        $current = app( Tenancy::class );
        app()->instance( Tenancy::class, new Tenancy( $this->tenant ) );

        try {
            Scout::sync( $this->model, $this->ids );
        } finally {
            app()->instance( Tenancy::class, $current );
        }
    }
}
