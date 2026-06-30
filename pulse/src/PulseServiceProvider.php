<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Commands\InstallPulse;
use Aimeos\Cms\Pulse\CmsAiCard;
use Aimeos\Cms\Pulse\CmsAuthCard;
use Aimeos\Cms\Pulse\CmsContactCard;
use Aimeos\Cms\Pulse\CmsElementCard;
use Aimeos\Cms\Pulse\CmsFileCard;
use Aimeos\Cms\Pulse\CmsJsonapiCard;
use Aimeos\Cms\Pulse\CmsPageCard;
use Aimeos\Cms\Pulse\CmsSearchCard;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsElementPulseRecorder;
use Aimeos\Cms\Recorders\CmsFilePulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsPagePulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider as Provider;


class PulseServiceProvider extends Provider
{
    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        $this->publishes( [
            $basedir . '/views/dashboard.blade.php' => resource_path( 'views/vendor/pulse/dashboard.blade.php' ),
        ], 'cms-pulse-dashboard' );

        $this->publishes( [
            $basedir . '/views/pulse' => resource_path( 'views/vendor/cms-pulse' ),
        ], 'cms-pulse-views' );

        $this->gate();
        $this->console();

        $this->app->booted( fn() => $this->pulse( $basedir ) );
    }


    protected function pulse( string $basedir ) : void
    {
        if( !$this->installed() ) {
            return;
        }

        $this->loadViewsFrom( $basedir . '/views/pulse', 'cms-pulse' );
        $this->components();

        $pulse = $this->pulseInstance();

        if( $pulse && method_exists( $pulse, 'register' ) )
        {
            $pulse->register( [
                CmsPagePulseRecorder::class => true,
                CmsElementPulseRecorder::class => true,
                CmsFilePulseRecorder::class => true,
                CmsAuthPulseRecorder::class => true,
                CmsAiPulseRecorder::class => true,
                CmsSearchPulseRecorder::class => true,
                CmsContactPulseRecorder::class => true,
                CmsJsonapiPulseRecorder::class => true,
            ] );
        }
    }


    protected function components() : void
    {
        if( !class_exists( \Livewire\Livewire::class ) ) {
            return;
        }

        \Livewire\Livewire::component( 'cms-page-card', CmsPageCard::class );
        \Livewire\Livewire::component( 'cms-element-card', CmsElementCard::class );
        \Livewire\Livewire::component( 'cms-file-card', CmsFileCard::class );
        \Livewire\Livewire::component( 'cms-auth-card', CmsAuthCard::class );
        \Livewire\Livewire::component( 'cms-ai-card', CmsAiCard::class );
        \Livewire\Livewire::component( 'cms-search-card', CmsSearchCard::class );
        \Livewire\Livewire::component( 'cms-contact-card', CmsContactCard::class );
        \Livewire\Livewire::component( 'cms-jsonapi-card', CmsJsonapiCard::class );
    }


    protected function console() : void
    {
        if( $this->app->runningInConsole() ) {
            $this->commands( [InstallPulse::class] );
        }
    }


    protected function gate() : void
    {
        if( !Gate::has( 'viewPulse' ) ) {
            Gate::define( 'viewPulse', fn( $user ) => Permission::can( '*', $user ) );
        }
    }


    protected function installed() : bool
    {
        return class_exists( \Laravel\Pulse\Pulse::class );
    }


    protected function pulseInstance() : ?object
    {
        if( $this->app->bound( \Laravel\Pulse\Pulse::class ) ) {
            return $this->app->make( \Laravel\Pulse\Pulse::class );
        }

        return $this->app->bound( 'pulse' ) ? $this->app->make( 'pulse' ) : null;
    }
}
