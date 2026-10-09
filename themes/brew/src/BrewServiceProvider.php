<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class BrewServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'brew' );
        View::addNamespace( 'brew', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'brew', '/vendor/cms/brew/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/brew' )], 'cms-theme' );
    }
}
