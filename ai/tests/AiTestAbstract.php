<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;


abstract class AiTestAbstract extends CmsTestAbstract
{
	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set('cms.config.locales', ['en', 'de'] );
		$app['config']->set('cms.ai.write', ['provider' => 'gemini', 'model' => 'test', 'api_key' => 'test']);
		$app['config']->set('cms.ai.refine', ['provider' => 'gemini', 'model' => 'test', 'api_key' => 'test']);
		$app['config']->set('cms.ai.describe', ['provider' => 'gemini', 'api_key' => 'test']);
		$app['config']->set('cms.ai.erase', ['provider' => 'clipdrop', 'api_key' => 'test']);
		$app['config']->set('cms.ai.imagine', ['provider' => 'clipdrop', 'api_key' => 'test']);
		$app['config']->set('cms.ai.inpaint', ['provider' => 'stabilityai', 'api_key' => 'test']);
		$app['config']->set('cms.ai.isolate', ['provider' => 'clipdrop', 'api_key' => 'test']);
		$app['config']->set('cms.ai.uncrop', ['provider' => 'clipdrop', 'api_key' => 'test']);
		$app['config']->set('cms.ai.upscale', ['provider' => 'clipdrop', 'api_key' => 'test']);
		$app['config']->set('cms.ai.transcribe', ['provider' => 'openai', 'api_key' => 'test']);
		$app['config']->set('cms.ai.translate', ['provider' => 'deepl', 'api_key' => 'test']);

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
			'Aimeos\Cms\AiServiceProvider',
			'Aimeos\Cms\GraphqlServiceProvider',
			'Nuwave\Lighthouse\LighthouseServiceProvider',
		] );
	}
}
