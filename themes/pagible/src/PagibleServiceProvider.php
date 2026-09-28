<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class PagibleServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'pagible' );
        View::addNamespace( 'pagible', $basedir . '/views' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'pagible', '/vendor/cms/pagible/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/pagible' )], 'cms-theme' );
    }
}
