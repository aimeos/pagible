<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class FlowServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'flow' );
        View::addNamespace( 'flow', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'flow', '/vendor/cms/flow/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/flow' )], 'cms-theme' );
    }
}
