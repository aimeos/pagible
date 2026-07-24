<?php

namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class JournalServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        Schema::register( $basedir, 'journal' );
        View::addNamespace( 'journal', $basedir . '/views' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/journal' )], 'cms-theme' );
    }
}
