<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Commands\InstallPulse;
use Aimeos\Cms\Pulse\CmsMetricCard;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsContentPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsRequestPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider as Provider;


class PulseServiceProvider extends Provider
{
    private const VIEW_PERMISSION = 'pulse:view';

    /**
     * @var list<class-string>
     */
    private const RECORDERS = [
        CmsContentPulseRecorder::class,
        CmsAuthPulseRecorder::class,
        CmsAiPulseRecorder::class,
        CmsSearchPulseRecorder::class,
        CmsContactPulseRecorder::class,
        CmsJsonapiPulseRecorder::class,
        CmsRequestPulseRecorder::class,
    ];


    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        Permission::register( self::VIEW_PERMISSION );

        $this->mergeConfigFrom( $basedir . '/config/cms/pulse.php', 'cms.pulse' );

        $this->publishes( [
            $basedir . '/config/cms/pulse.php' => config_path( 'cms/pulse.php' ),
        ], 'cms-config' );

        $this->publishes( [
            $basedir . '/views/dashboard.blade.php' => resource_path( 'views/vendor/pulse/dashboard.blade.php' ),
        ], 'cms-pulse-dashboard' );

        $this->publishes( [
            $basedir . '/views/pulse' => resource_path( 'views/vendor/cms-pulse' ),
        ], 'cms-pulse-views' );

        $this->console();

        $this->app->booted( fn() => $this->gate() );
        $this->app->booted( fn() => $this->pulse( $basedir ) );
    }


    protected function pulse( string $basedir ) : void
    {
        if( !$this->installed() ) {
            return;
        }

        $this->loadViewsFrom( $basedir . '/views/pulse', 'cms-pulse' );

        if( class_exists( \Livewire\Livewire::class ) && $this->app->bound( 'livewire.finder' ) ) {
            \Livewire\Livewire::component( 'cms-metric-card', CmsMetricCard::class );
        }

        if( config( 'pulse.enabled' ) === false ) {
            return;
        }

        $pulse = $this->pulseInstance();

        if( $pulse && method_exists( $pulse, 'register' ) )
        {
            $pulse->register( $this->recorders() );
        }
    }


    /**
     * @return array<class-string, bool>
     */
    protected function recorders() : array
    {
        $recorders = [];

        foreach( self::RECORDERS as $recorder )
        {
            if( $this->recorderAvailable( $recorder ) ) {
                $recorders[$recorder] = true;
            }
        }

        return $recorders;
    }


    /**
     * @param class-string $recorder
     */
    protected function recorderAvailable( string $recorder ) : bool
    {
        $listen = ( new \ReflectionClass( $recorder ) )->getDefaultProperties()['listen'] ?? [];

        if( !is_array( $listen ) || $listen === [] ) {
            return false;
        }

        foreach( $listen as $event )
        {
            if( !is_string( $event ) || !class_exists( $event ) ) {
                return false;
            }
        }

        return true;
    }


    protected function console() : void
    {
        if( $this->app->runningInConsole() ) {
            $this->commands( [InstallPulse::class] );
        }
    }


    protected function gate() : void
    {
        if( !Gate::has( 'viewPulse' ) || $this->defaultPulseGate() ) {
            Gate::define( 'viewPulse', fn( $user ) => $this->canViewPulse( $user ) );
        }
    }


    protected function canViewPulse( ?Authenticatable $user ) : bool
    {
        if( !Permission::can( self::VIEW_PERMISSION, $user ) ) {
            return false;
        }

        $tenant = Tenancy::value();

        return $tenant === ''
            ? Tenancy::$callback === null
            : Tenancy::allows( $user, $tenant );
    }


    protected function defaultPulseGate() : bool
    {
        try {
            $gate = Gate::getFacadeRoot();
            $property = new \ReflectionProperty( $gate, 'abilities' );
            $callback = ( $property->getValue( $gate ) )['viewPulse'] ?? null;

            return $callback instanceof \Closure
                && ( new \ReflectionFunction( $callback ) )->getClosureScopeClass()?->getName()
                    === \Laravel\Pulse\PulseServiceProvider::class;
        } catch( \Throwable ) {
            return false;
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
