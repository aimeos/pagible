<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class NewsServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'news' );
        View::addNamespace( 'news', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/news' )], 'cms-theme' );
    }
}
