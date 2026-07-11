<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


abstract class ThemeTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set('cms.locales', ['en', 'de'] );
	}


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\ThemeServiceProvider',
		] );
	}
}
