<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class GlassServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'glass' );
        View::addNamespace( 'glass', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/glass' )], 'cms-theme' );
    }
}
