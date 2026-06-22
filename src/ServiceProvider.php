<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;


class ServiceProvider extends Provider
{
	public function boot(): void
	{
		if( $this->app->runningInConsole() )
		{
			$this->commands( [
				\Aimeos\Cms\Commands\Benchmark::class,
				\Aimeos\Cms\Commands\Install::class,
				\Aimeos\Cms\Commands\Serve::class,
			] );
		}
	}
}
