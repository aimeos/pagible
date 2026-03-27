<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as Provider;

class AdminServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->loadViewsFrom( $basedir . '/views', 'cms' );
        $this->loadRoutesFrom( $basedir . '/routes/admin.php' );

        $this->publishes( [$basedir . '/app/dist' => public_path( 'vendor/cms/admin' )], 'cms-admin' );
        $this->publishes( [$basedir . '/config/cms/admin.php' => config_path( 'cms/admin.php' )], 'cms-admin-config' );
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/admin.php', 'cms.admin' );
    }
}
