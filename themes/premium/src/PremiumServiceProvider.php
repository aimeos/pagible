<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class PremiumServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'premium' );
        View::addNamespace( 'premium', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/premium' )], 'cms-theme' );
    }
}
