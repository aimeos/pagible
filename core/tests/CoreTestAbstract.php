<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;

abstract class CoreTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set('cms.locales', ['en', 'de', 'fr'] );

		\Aimeos\Cms\Schema::register( dirname( __DIR__, 2 ) . '/theme', 'cms' );
	}


	protected function root() : PageView
	{
		return PageView::where( 'tag', 'root' )->firstOrFail();
	}
}
