<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Commands\InstallPulse;
use Aimeos\Cms\Pulse\CmsMetricCard;
use Aimeos\Cms\Pulse\Recorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider as Provider;
use Livewire\Livewire;


class PulseServiceProvider extends Provider
{
    private const VIEW_PERMISSION = 'pulse:view';

    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        Permission::register( self::VIEW_PERMISSION );

        $this->publishes( [
            $basedir . '/config/cms/pulse.php' => config_path( 'cms/pulse.php' ),
        ], 'cms-config' );

        $this->publishes( [
            $basedir . '/views/dashboard.blade.php' => resource_path( 'views/vendor/pulse/dashboard.blade.php' ),
        ], 'cms-pulse-dashboard' );

        $this->publishes( [
            $basedir . '/views/pulse' => resource_path( 'views/vendor/cms-pulse' ),
        ], 'cms-pulse-views' );

        $this->loadViewsFrom( $basedir . '/views/pulse', 'cms-pulse' );
        Livewire::component( 'cms-metric-card', CmsMetricCard::class );

        $this->console();

        $this->app->booted( fn() => $this->gate() );
    }


    public function register() : void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/pulse.php', 'cms.pulse' );

        $this->app->booting( function() {
            $recorders = config( 'pulse.recorders', [] );

            config( ['pulse.recorders' => ( is_array( $recorders ) ? $recorders : [] ) + [
                Recorder::class => true,
            ]] );
        } );
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


    protected function console() : void
    {
        if( $this->app->runningInConsole() ) {
            $this->commands( [InstallPulse::class] );
        }
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


    protected function gate() : void
    {
        if( !Gate::has( 'viewPulse' ) || $this->defaultPulseGate() ) {
            Gate::define( 'viewPulse', fn( $user ) => $this->canViewPulse( $user ) );
        }
    }
}
