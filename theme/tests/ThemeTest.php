<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Schema;
use Aimeos\Cms\Theme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;


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


    public function testDiscoverRefreshesUploadedSchemas()
    {
        Storage::fake( 'themes' );
        config( ['cms.theme.disk' => 'themes', 'cms.theme.ttl' => 60] );

        $disk = Storage::disk( 'themes' );
        $disk->put( 'custom/schema.json', json_encode( [
            'label' => 'Custom',
            'content' => ['first' => ['fields' => []]],
        ] ) );
        $disk->put( 'custom/preview.webp', 'preview' );

        $theme = Schema::get( 'custom' );

        $this->assertSame( 'Custom', $theme['label'] ?? null );
        $this->assertNotNull( $theme['preview'] ?? null );
        $this->assertArrayHasKey( 'custom::first', $theme['content'] ?? [] );

        $this->assertArrayHasKey( 'custom::first', Schema::schemas( 'custom', 'content' ) );

        $disk->put( 'custom/schema.json', json_encode( [
            'label' => 'Changed',
            'content' => ['second' => ['fields' => []]],
        ] ) );
        Cache::forget( 'cms-themes_test' );

        $schemas = Schema::schemas( 'custom', 'content' );

        $this->assertArrayHasKey( 'custom::second', $schemas );
        $this->assertArrayNotHasKey( 'custom::first', $schemas );
    }


    public function testRateLimiters()
    {
        $this->assertNotNull( RateLimiter::limiter( 'cms-contact' ) );
        $this->assertNotNull( RateLimiter::limiter( 'cms-search' ) );
        $this->assertNotNull( RateLimiter::limiter( 'cms-sitemap' ) );
    }


	public function testBladeTextDirectiveDoesNotInsertBreakTags()
	{
		$template = '@text($text){{-- no-break-tags --}}';

		$this->assertEquals( "one\ntwo", Blade::render( $template, ['text' => "one\ntwo"], true ) );
		$this->assertEquals( "one &amp; two\n<strong>three</strong>", Blade::render( $template, ['text' => "one & two\n**three**"], true ) );
	}


	public function testMarkdownDirectiveTrimsOuterBreaks()
	{
		$template = '<div class="text">@markdown($text)</div>';

		$this->assertEquals( '<div class="text"><p>one</p></div>', Blade::render( $template, ['text' => 'one'], true ) );
	}


	public function testTextClassNodesAreInline()
	{
		foreach( glob( dirname( __DIR__ ) . '/views/*.blade.php' ) ?: [] as $path ) {
			$view = file_get_contents( $path );

			preg_match_all( '/<(?<tag>[a-z][a-z0-9-]*)\b[^>]*class="[^"]*\btext\b[^"]*"[^>]*>.*?<\/\k<tag>>/s', $view, $matches );

			foreach( $matches[0] ?? [] as $node ) {
				if( !str_contains( $node, '@markdown(' ) ) {
					continue;
				}

				$this->assertStringNotContainsString( "\n", $node, $path );
				$this->assertStringNotContainsString( "\r", $node, $path );
			}
		}
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


	public function testViewsRejectsPathTraversal()
	{
		// A page theme is user-controlled and flows into a storage path that is
		// recursively cleaned up. Names outside the [a-zA-Z0-9-] whitelist must be
		// returned verbatim without ever touching the filesystem (no traversal).
		config( ['cms.theme.disk' => 'local'] );

		foreach( ['../../../..', '../etc', 'foo/bar', 'foo\\bar', "foo\0bar", '.', '..', ''] as $name ) {
			$this->assertEquals( $name, Theme::views( $name ) );
		}

		$this->assertDirectoryDoesNotExist( storage_path( 'app/cms-themes' ) );
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
		$this->assertArrayHasKey( 'description', $schemas['meta']['meta-tags']['fields'] );
		$this->assertArrayHasKey( 'description', $schemas['meta']['social-media']['fields'] );
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
