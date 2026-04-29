<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;


class BackupServiceProvider extends Provider
{
    public function boot(): void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\Backup::class,
                \Aimeos\Cms\Commands\Restore::class,
            ] );
        }
    }
}
