<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class AimeosServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'aimeos' );
        View::addNamespace( 'aimeos', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/aimeos' )], 'cms-theme' );
    }
}
