<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;


abstract class CashierTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set( 'cms.cashier.provider', 'stripe' );
		$app['config']->set( 'cms.cashier.success_url', '/success' );
		$app['config']->set( 'cms.cashier.cancel_url', '/cancel' );
	}


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\ThemeServiceProvider',
			'Aimeos\Cms\CashierServiceProvider',
		] );
	}
}
