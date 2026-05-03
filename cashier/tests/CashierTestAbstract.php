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
		$app['config']->set( 'cms.cashier.products', [
			'price_test123' => ['once' => true, 'action' => 'premium'],
		] );

		\Illuminate\Support\Facades\Route::get( 'login', fn() => '' )->name( 'login' );
	}


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\ThemeServiceProvider',
			'Aimeos\Cms\CashierServiceProvider',
		] );
	}
}
