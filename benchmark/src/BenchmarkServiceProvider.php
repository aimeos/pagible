<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;


class BenchmarkServiceProvider extends Provider
{
    public function boot() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\Benchmark::class,
                \Aimeos\Cms\Commands\BenchmarkCore::class,
                \Aimeos\Cms\Commands\BenchmarkGraphql::class,
                \Aimeos\Cms\Commands\BenchmarkJsonapi::class,
                \Aimeos\Cms\Commands\BenchmarkMcp::class,
                \Aimeos\Cms\Commands\BenchmarkSearch::class,
                \Aimeos\Cms\Commands\BenchmarkTheme::class,
            ] );
        }
    }
}
