<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;


abstract class GraphqlTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set('cms.locales', ['en', 'de'] );
		$app['config']->set('scout.driver', 'collection');

		$app['config']->set('cms.schemas.content.heading', [
			'group' => 'basic',
			'fields' => [
				'title' => [
					'type' => 'string',
					'min' => 1,
				],
				'level' => [
					'type' => 'select',
					'required' => true,
				],
			],
		]);
	}


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\GraphqlServiceProvider',
			'Nuwave\Lighthouse\LighthouseServiceProvider',
		] );
	}
}
