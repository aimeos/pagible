<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;


class ServiceProvider extends Provider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 */
	protected bool $defer = false;


	/**
	 * Bootstrap the application events.
	 */
	public function boot(): void
	{
		$basedir = dirname( __DIR__ );

		$this->loadBladeDirectives();
		$this->loadViewsFrom( $basedir . '/views', 'cms' );
		$this->loadRoutesFrom( $basedir . '/routes/web.php');
		$this->loadMigrationsFrom( $basedir . '/database/migrations' );
		$this->loadJsonTranslationsFrom( $basedir . '/lang' );

		$this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/theme' )], 'public' );
		$this->publishes( [$basedir . '/config/cms.php' => config_path( 'cms.php' )], 'config' );
		$this->publishes( [$basedir . '/admin/dist' => public_path( 'vendor/cms/admin' )], 'admin' );
		$this->publishes( [$basedir . '/graphql' => base_path( 'graphql' )], 'admin' );

		$this->rateLimiter();
		$this->console();
		$this->scout();

		$this->app->make('events')->listen(
			\Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces::class,
			fn() => 'Aimeos\\Cms\\GraphQL\\Directives'
		);
	}


	/**
	 * Register the service provider.
	 */
	public function register()
	{
		$this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms.php', 'cms' );

		$this->app->scoped( \Aimeos\Cms\Tenancy::class, function() {
			$callback = \Aimeos\Cms\Tenancy::$callback;
			return new \Aimeos\Cms\Tenancy( $callback ? $callback() : '' );
		} );

		config(['jsonapi.servers' => array_merge(
			config('jsonapi.servers', []) ,
			['cms' => \Aimeos\Cms\JsonApi\V1\Server::class]),
		]);
	}


	/**
	 * Registers the commands
	 */
	protected function console() : void
	{
		if( $this->app->runningInConsole() )
		{
			 $this->commands( [
				\Aimeos\Cms\Commands\Description::class,
				\Aimeos\Cms\Commands\Index::class,
				\Aimeos\Cms\Commands\Install::class,
				\Aimeos\Cms\Commands\Publish::class,
				\Aimeos\Cms\Commands\Serve::class,
				\Aimeos\Cms\Commands\User::class,
				\Aimeos\Cms\Commands\WpImport::class,
				\Aimeos\Cms\Commands\Demo::class,
			] );
		}
	}


	/**
	 * Register Blade directives
	 */
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
				echo (new \League\CommonMark\GithubFlavoredMarkdownConverter([
					'html_input' => 'strip',
					'allow_unsafe_links' => false,
					'max_nesting_level' => 25
				]))->convert($expression ?? '');
			?>";
		} );
	}


	/**
	 * Register rate limiters
	 */
	protected function rateLimiter(): void
	{
		RateLimiter::for( 'cms-admin', fn( $request ) =>
			Limit::perMinute( 120 )->by( $request->user()?->getAuthIdentifier() ?: $request->ip() )
		);

		RateLimiter::for( 'cms-ai', fn( $request ) =>
			Limit::perMinute( 10 )->by( $request->user()?->getAuthIdentifier() ?: $request->ip() )
		);

		RateLimiter::for( 'cms-contact', fn( $request ) =>
			Limit::perMinute( 2 )->by( $request->ip() )
		);

		RateLimiter::for( 'cms-jsonapi', fn( $request ) =>
			Limit::perMinute( 60 )->by( $request->ip() )
		);

		RateLimiter::for( 'cms-login', fn( $request ) =>
			Limit::perMinute( 10 )->by( $request->ip() )
		);

		RateLimiter::for( 'cms-proxy', fn( $request ) =>
			Limit::perMinute( 30 )->by( $request->ip() )
		);

		RateLimiter::for( 'cms-search', fn( $request ) =>
			Limit::perMinute( 60 )->by( $request->ip() )
		);
	}


	/**
	 * Register Scout engine and macros
	 */
	protected function scout(): void
	{
		app(\Laravel\Scout\EngineManager::class)->extend('cms', function () {
			return new \Aimeos\Cms\Scout\CmsEngine();
		});

		// handle split content/draft search
		\Laravel\Scout\Builder::macro('searchFields', function( string ...$fields ) {
			return match( config('scout.driver') ) {
				'meilisearch' => $this->options( ['attributesToSearchOn' => $fields] ),
				'typesense' => $this->options( ['query_by' => implode( ',', $fields )] ),
				'algolia' => $this->options( ['restrictSearchableAttributes' => $fields] ),
				'cms' => $this->where( 'latest', in_array( 'draft', $fields ) ),
				default => $this,
			};
		});
	}
}
