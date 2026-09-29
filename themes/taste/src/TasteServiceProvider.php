<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;


class TasteServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'taste' );
        View::addNamespace( 'taste', $basedir . '/views' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'taste', '/vendor/cms/taste/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/taste' )], 'cms-theme' );
    }
}
