<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


abstract class CashierTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

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
