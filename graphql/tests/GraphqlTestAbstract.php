<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


abstract class GraphqlTestAbstract extends CmsTestAbstract
{
	use MakesGraphQLRequests;
	use RefreshesSchemaCache;


	protected function defineEnvironment( $app )
	{
		parent::defineEnvironment( $app );

		$app['config']->set('cms.locales', ['en', 'de'] );
		$app['config']->set('scout.driver', 'collection');
		$app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
		$app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
		$app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
		$app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );

		\Aimeos\Cms\Schema::register( dirname( __DIR__, 2 ) . '/theme', 'cms' );
	}


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\GraphqlServiceProvider',
			'Nuwave\Lighthouse\LighthouseServiceProvider',
		] );
	}
}
