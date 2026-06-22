<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


abstract class CoreTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set('cms.locales', ['en', 'de'] );

		\Aimeos\Cms\Schema::register( dirname( __DIR__, 2 ) . '/theme', 'cms' );
	}
}
