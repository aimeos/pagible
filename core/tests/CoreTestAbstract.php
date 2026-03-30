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

		$app['config']->set('cms.config.locales', ['en', 'de'] );

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
}
