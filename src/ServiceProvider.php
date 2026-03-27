<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
				\Aimeos\Cms\Commands\Install::class,
				\Aimeos\Cms\Commands\Serve::class,
			] );
		}
	}
}
