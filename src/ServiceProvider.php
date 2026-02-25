<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;


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


		if( $this->app->runningInConsole() )
		{
			$this->commands( [
				\Aimeos\Cms\Commands\Index::class,
				\Aimeos\Cms\Commands\Install::class,
				\Aimeos\Cms\Commands\Publish::class,
				\Aimeos\Cms\Commands\Serve::class,
				\Aimeos\Cms\Commands\User::class,
			] );
		}

        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::skipWhen( function( $request ) {
            return $request->is( trim( config( 'lighthouse.route.uri' ), '/' ) );
        } );
	}


	/**
	 * Register the service provider.
	 */
	public function register()
	{
		config(['jsonapi.servers' => array_merge(
			config('jsonapi.servers', []) ,
			['cms' => \Aimeos\Cms\JsonApi\V1\Server::class]),
		]);
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
					'html_input' => 'allow',
					'allow_unsafe_links' => false,
					'max_nesting_level' => 25
				]))->convert($expression ?? '');
			?>";
		} );
	}
}
