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

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'style', '/vendor/cms/style/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/style' )], 'cms-theme' );
    }
}
