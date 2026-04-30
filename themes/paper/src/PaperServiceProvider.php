<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class PaperServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'paper' );
        View::addNamespace( 'paper', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/paper' )], 'cms-theme' );
    }
}
