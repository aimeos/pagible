<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class EstateServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'estate' );
        View::addNamespace( 'estate', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/estate' )], 'cms-theme' );
    }
}
