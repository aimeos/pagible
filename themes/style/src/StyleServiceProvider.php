<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class StyleServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'style' );
        View::addNamespace( 'style', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/style' )], 'cms-theme' );
    }
}
