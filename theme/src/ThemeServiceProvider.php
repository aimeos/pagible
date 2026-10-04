<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Events\CmsContact;
use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Listeners\ContactLogListener;
use Aimeos\Cms\Listeners\SearchLogListener;
use Aimeos\Cms\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;

class ThemeServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->loadBladeDirectives();
        $this->rateLimiter();
        Schema::source( fn() => Theme::discover() );
        Schema::register( $basedir, 'cms' );

        View::addNamespace( 'cms', $basedir . '/views' );

        $this->loadMigrationsFrom( $basedir . '/database/migrations' );
        $this->loadJsonTranslationsFrom( $basedir . '/lang' );

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/theme' )], 'cms-theme' );
        $this->publishes( [$basedir . '/config/cms/theme.php' => config_path( 'cms/theme.php' )], 'cms-config' );

        // Defer catch-all route to ensure it loads last
        $this->app->booted(function() use ($basedir) {
            $this->loadRoutesFrom( $basedir . '/routes/theme.php' );
        });

        Event::listen( PageInvalidated::class, function( PageInvalidated $event ) {
            try {
                PageCache::invalidate( $event->domain, $event->paths, $event->tenant );
            } catch( \Throwable $e ) {
                report( $e );
            }
        } );

        $this->watch();
        $this->console();
    }

    protected function watch() : void
    {
        Watch::listen( [
            CmsSearch::class => SearchLogListener::class,
            CmsContact::class => ContactLogListener::class,
        ], 'cms.theme.watch' );
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkTheme::class,
                \Aimeos\Cms\Commands\Demo::class,
                \Aimeos\Cms\Commands\InstallTheme::class,
            ] );
        }
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/theme.php', 'cms.theme' );

        // Page languages are BCP 47 tags ("pt-BR") but Laravel's plural rules expect "pt_BR"
        $this->app->extend( 'translator', fn( $translator ) => tap( $translator )->setSelector( new MessageSelector() ) );
    }

    protected function rateLimiter(): void
    {
        RateLimiter::for( 'cms-contact', fn( $request ) =>
            Limit::perMinute( 2 )->by( $request->ip() )
        );

        RateLimiter::for( 'cms-search', fn( $request ) =>
            Limit::perMinute( 60 )->by( $request->ip() )
        );

        RateLimiter::for( 'cms-sitemap', fn( $request ) =>
            Limit::perMinute( 10 )->by( $request->ip() )
        );
    }

    protected function loadBladeDirectives(): void
    {
        Blade::directive( 'localDate', function( $expression ) {
            // Style names and the default use ICU, other formats are Carbon isoFormat() patterns
            return "<?php
                \$__args = [$expression];

                try {
                    \$__date = \\Carbon\\Carbon::parse(\$__args[0] ?? 'now');
                    \$__locale = app()->getLocale();
                    \$__styles = ['short' => \\IntlDateFormatter::SHORT, 'medium' => \\IntlDateFormatter::MEDIUM, 'long' => \\IntlDateFormatter::LONG, 'full' => \\IntlDateFormatter::FULL];

                    if( isset(\$__args[1]) && !isset(\$__styles[\$__args[1]]) ) {
                        // Carbon uses Latin script for Serbian but the theme translations are Cyrillic
                        echo e(\$__date->locale(strtolower(\$__locale) === 'sr' ? 'sr_Cyrl' : \$__locale)->isoFormat(\$__args[1]));
                    } else {
                        echo e((new \\IntlDateFormatter(\$__locale, \$__styles[\$__args[1] ?? ''] ?? \\IntlDateFormatter::NONE, \\IntlDateFormatter::NONE, \$__date->getTimezone(), null,
                            isset(\$__args[1]) ? null : (new \\IntlDatePatternGenerator(\$__locale))->getBestPattern('dMMMM')))->format(\$__date));
                    }
                } catch( \\Carbon\\Exceptions\\InvalidFormatException \$e ) {
                    // Element data isn't validated against the field type, so show unparsable values as they are
                    echo e((string) \$__args[0]);
                }
            ?>";
        } );

        Blade::directive( 'markdown', function( $expression ) {
            return "<?php
                if( !((\$__cmsMarkdown ?? null) instanceof \League\CommonMark\GithubFlavoredMarkdownConverter) ) {
                    \$__cmsMarkdown = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                        'max_nesting_level' => 25,
                        'renderer' => [
                            'block_separator' => '',
                            'inner_separator' => ''
                        ]
                    ]);
                }
                echo trim((string) \$__cmsMarkdown->convert($expression ?? ''));
            ?>";
        } );

        Blade::directive( 'text', function( $expression ) {
            return "<?php
                \$__cmsTextVal = $expression ?? '';
                if( \$__cmsTextVal === '' || strpbrk( \$__cmsTextVal, '*_\`[]()!<>&\\\\~\"' ) === false ) {
                    echo trim((string) \$__cmsTextVal);
                } else {
                    if( !((\$__cmsText ?? null) instanceof \League\CommonMark\MarkdownConverter) ) {
                        \$__cmsTextEnv = new \\League\\CommonMark\\Environment\\Environment([
                            'html_input' => 'strip',
                            'allow_unsafe_links' => false,
                            'max_nesting_level' => 3,
                            'renderer' => [
                                'block_separator' => ''
                            ]
                        ]);
                        \$__cmsTextEnv->addExtension( new \\League\\CommonMark\\Extension\\InlinesOnly\\InlinesOnlyExtension() );
                        \$__cmsText = new \\League\\CommonMark\\MarkdownConverter( \$__cmsTextEnv );
                    }
                    echo trim((string) \$__cmsText->convert( \$__cmsTextVal ));
                }
            ?>";
        } );
    }
}
