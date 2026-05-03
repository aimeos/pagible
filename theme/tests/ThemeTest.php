<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Schema;
use Aimeos\Cms\Theme;


class ThemeTest extends ThemeTestAbstract
{
	public function testRegister()
	{
		$theme = Schema::get( 'cms' );

		$this->assertIsArray( $theme );
		$this->assertEquals( 'Default', $theme['label'] );
		$this->assertEquals( 'Default Pagible CMS theme', $theme['description'] );
	}


	public function testRegisterTypes()
	{
		$theme = Schema::get( 'cms' );

		$this->assertArrayHasKey( 'types', $theme );
		$this->assertArrayHasKey( 'page', $theme['types'] );
		$this->assertArrayHasKey( 'docs', $theme['types'] );
		$this->assertArrayHasKey( 'blog', $theme['types'] );
	}


	public function testRegisterSchemas()
	{
		$schemas = Schema::schemas( section: 'content' );

		$this->assertArrayHasKey( 'heading', $schemas );
		$this->assertArrayHasKey( 'text', $schemas );
		$this->assertArrayHasKey( 'fields', $schemas['heading'] );
	}


	public function testRegisterSchemaNamespacing()
	{
		$path = $this->createTestTheme( 'corporate', [
			'label' => 'Corporate',
			'content' => [
				'xylotron' => ['group' => 'content', 'fields' => ['price' => ['type' => 'string']]],
			],
		] );

		Schema::register( $path, 'corporate' );

		$schemas = Schema::schemas( section: 'content' );

		$this->assertArrayHasKey( 'corporate::xylotron', $schemas );
		$this->assertArrayNotHasKey( 'xylotron', $schemas );
	}


	public function testRegisterNoOverride()
	{
		$path = $this->createTestTheme( 'other', [
			'label' => 'Other',
			'content' => [
				'heading' => ['group' => 'custom', 'fields' => ['title' => ['type' => 'number']]],
			],
		] );

		Schema::register( $path, 'other' );

		$schemas = Schema::schemas( section: 'content' );

		// Core 'heading' should win, not be overridden
		$this->assertEquals( 'basic', $schemas['heading']['group'] );
	}


	public function testAll()
	{
		$all = Schema::all();

		$this->assertArrayHasKey( 'cms', $all );
		$this->assertIsArray( $all['cms'] );
	}


	public function testGet()
	{
		$this->assertIsArray( Schema::get( 'cms' ) );
		$this->assertNull( Schema::get( 'nonexistent' ) );
	}


	public function testLayouts()
	{
		$layouts = Theme::layouts( 'cms' );

		$this->assertArrayHasKey( 'page', $layouts );
		$this->assertArrayHasKey( 'docs', $layouts );
		$this->assertArrayHasKey( 'blog', $layouts );
	}


	public function testViewsGlobal()
	{
		$this->assertEquals( 'cms', Theme::views( 'cms' ) );
	}


	public function testMetadata()
	{
		$theme = Schema::get( 'cms' );

		$this->assertEquals( 'Aimeos GmbH', $theme['maintainer'] );
		$this->assertEquals( 'info@aimeos.com', $theme['email'] );
		$this->assertEquals( 'https://aimeos.com', $theme['website'] );
	}


	public function testSchemasAllSections()
	{
		$schemas = Schema::schemas();

		$this->assertArrayHasKey( 'content', $schemas );
		$this->assertArrayHasKey( 'meta', $schemas );
		$this->assertArrayHasKey( 'heading', $schemas['content'] );
		$this->assertArrayHasKey( 'meta-tags', $schemas['meta'] );
	}


	public function testSchemasFilterByTheme()
	{
		$schemas = Schema::schemas( name: 'cms', section: 'content' );

		$this->assertArrayHasKey( 'heading', $schemas );
		$this->assertArrayHasKey( 'text', $schemas );
	}


	/**
	 * Creates a temporary test theme directory with a schema.json file.
	 *
	 * @param string $name Theme name
	 * @param array<string, mixed> $data Theme JSON data
	 * @return string Path to the temporary theme directory
	 */
	protected function createTestTheme( string $name, array $data ) : string
	{
		$path = sys_get_temp_dir() . '/cms-test-theme-' . $name;

		if( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		if( !is_dir( $path . '/views' ) ) {
			mkdir( $path . '/views', 0755, true );
		}

		file_put_contents( $path . '/schema.json', json_encode( $data ) );

		return $path;
	}
}
