<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class BoldServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'bold' );
        View::addNamespace( 'bold', $basedir . '/views' );

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( 'bold', '/vendor/cms/bold/i18n/{locale}.json' );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/bold' )], 'cms-theme' );
    }
}
