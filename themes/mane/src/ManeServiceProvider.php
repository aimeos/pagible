<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class ManeServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'mane' );
        View::addNamespace( 'mane', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'mane', '/vendor/cms/mane/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/mane' )], 'cms-theme' );
    }
}
