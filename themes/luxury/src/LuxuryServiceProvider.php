<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class LuxuryServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'luxury' );
        View::addNamespace( 'luxury', $basedir . '/views' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'luxury', '/vendor/cms/luxury/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/luxury' )], 'cms-theme' );
    }
}
