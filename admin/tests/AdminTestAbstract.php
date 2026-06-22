<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


abstract class AdminTestAbstract extends CmsTestAbstract
{
	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\AdminServiceProvider',
			'Aimeos\Cms\GraphqlServiceProvider',
			'Nuwave\Lighthouse\LighthouseServiceProvider',
		] );
	}
}
