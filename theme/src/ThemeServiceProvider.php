<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class ThemeServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        RateLimiter::for( 'cms-sitemap', fn( $request ) =>
            Limit::perMinutes( 5, 1 )->by( $request->ip() )
        );

        $this->loadBladeDirectives();
        Schema::register( $basedir, 'cms' );
        View::addNamespace( 'cms', $basedir . '/views' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/theme' )], 'cms-theme' );
        $this->publishes( [$basedir . '/config/cms/theme.php' => config_path( 'cms/theme.php' )], 'cms-config' );

        // Defer catch-all route to ensure it loads last
        $this->app->booted(function() use ($basedir) {
            $this->loadRoutesFrom( $basedir . '/routes/theme.php' );
        });

        $this->console();
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkTheme::class,
                \Aimeos\Cms\Commands\InstallTheme::class,
            ] );
        }
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/theme.php', 'cms.theme' );
    }

    protected function loadBladeDirectives(): void
    {
        Blade::directive( 'localDate', function( $expression ) {
            return "<?php
                \$__args = [$expression];
                echo \\Carbon\\Carbon::parse(\$__args[0] ?? 'now')
                    ->locale(app()->getLocale())
                    ->isoFormat(\$__args[1] ?? 'D MMMM');
            ?>";
        } );

        Blade::directive( 'markdown', function( $expression ) {
            return "<?php
                static \$__cmsMarkdown = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                    'max_nesting_level' => 25
                ]);
                echo \$__cmsMarkdown->convert($expression ?? '');
            ?>";
        } );
    }
}
