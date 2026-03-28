<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class ImportServiceProvider extends Provider
{
    public function boot(): void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\WpImport::class,
            ] );
        }
    }
}
