<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class BoldServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'bold' );
        View::addNamespace( 'bold', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/bold' )], 'cms-theme' );
    }
}
