<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class SolidServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'solid' );
        View::addNamespace( 'solid', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'solid', '/vendor/cms/solid/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/solid' )], 'cms-theme' );
    }
}
