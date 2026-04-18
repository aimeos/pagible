<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
