<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Commands\DrupalImport;
use Aimeos\Cms\Commands\T3Import;
use Aimeos\Cms\Commands\WpImport;
use Illuminate\Support\ServiceProvider as Provider;

class ImportServiceProvider extends Provider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DrupalImport::class,
                T3Import::class,
                WpImport::class,
            ]);
        }
    }
}
