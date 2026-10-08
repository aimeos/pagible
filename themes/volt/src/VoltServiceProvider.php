<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class VoltServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'volt' );
        View::addNamespace( 'volt', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'volt', '/vendor/cms/volt/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/volt' )], 'cms-theme' );
    }
}
