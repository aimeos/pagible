<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Requests\ContactRequest;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Theme;
use Aimeos\Cms\Validation;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Carbon\Carbon;
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


	public function testRegisterSecurityConfig() : void
	{
		$security = Schema::schemas( section: 'config' )['security'];

		$this->assertSame( 'expert', $security['group'] );
		$this->assertSame( 'url', $security['fields']['contact']['type'] );
		$this->assertSame( ['https'], $security['fields']['contact']['allowed'] );
		$this->assertTrue( $security['fields']['contact']['absolute'] );
		$this->assertTrue( $security['fields']['contact']['required'] );
		$this->assertSame( 'string', $security['fields']['email']['type'] );
		$this->assertArrayNotHasKey( 'required', $security['fields']['email'] );
		$this->assertSame( 'date', $security['fields']['expires']['type'] );
		$this->assertTrue( $security['fields']['expires']['required'] );

		foreach( ['encryption', 'acknowledgments', 'canonical', 'policy', 'hiring', 'csaf'] as $name )
		{
			$this->assertSame( 'url', $security['fields'][$name]['type'] );
			$this->assertSame( ['https'], $security['fields'][$name]['allowed'] );
			$this->assertTrue( $security['fields'][$name]['absolute'] );
		}

		$this->assertArrayHasKey( 'preferred-languages', $security['fields'] );
		$this->assertArrayHasKey( 'csaf', $security['fields'] );
	}


	public function testRegisterCardsUrl()
	{
		$url = Schema::get( 'cms' )['content']['cards']['fields']['cards']['item']['url'];
		$content = Validation::page( ['content' => [[
			'type' => 'cards',
			'data' => ['cards' => [['title' => 'Linked', 'url' => '/target']]],
		]]] )['content'];

		$this->assertSame( 'url', $url['type'] );
		$this->assertArrayNotHasKey( 'required', $url );
		$this->assertSame( '/target', $content[0]->data->cards[0]->url );
	}


	public function testRegisterContactFields()
	{
		$fields = Schema::get( 'cms' )['content']['contact']['fields'];
		$mandatory = $fields['mandatory'];
		$optional = $fields['optional'];

		$this->assertSame( 'combobox', $mandatory['type'] );
		$this->assertSame( 'Mandatory fields', $mandatory['label'] );
		$this->assertTrue( $mandatory['multiple'] );
		$this->assertSame( 20, $mandatory['max'] );
		$this->assertSame( ['name', 'email'], $mandatory['default'] );
		$this->assertSame( 'combobox', $optional['type'] );
		$this->assertSame( 'Optional fields', $optional['label'] );
		$this->assertTrue( $optional['multiple'] );
		$this->assertSame( 20, $optional['max'] );
		$this->assertSame( [], $optional['default'] );
		$this->assertSame(
			['name', 'company', 'telephone', 'email', 'subject'],
			array_column( $mandatory['options'], 'value' )
		);
		$this->assertSame( $mandatory['options'], $optional['options'] );
	}


	public function testRegisterMapField()
	{
		$fields = Schema::get( 'cms' )['content']['map']['fields'];

		$this->assertSame( 'map', $fields['location']['type'] );
		$this->assertTrue( $fields['location']['required'] );
		$this->assertSame( 15, $fields['location']['zoom'] );
	}


	public function testMapRendersOpenStreetMap()
	{
		$page = ( new Page() )->forceFill( ['lang' => 'en'] );
		$data = (object) [
			'title' => 'Find us',
			'text' => 'Kastanienallee 48',
			'location' => (object) [
				'latitude' => 52.538456,
				'longitude' => 13.409564,
				'zoom' => 16,
			],
			'button' => 'Open in OpenStreetMap',
		];

		$html = Blade::render(
			"@include('cms::map', ['data' => \$data, 'page' => \$page])\n@stack('foot')",
			compact( 'data', 'page' ),
			true,
		);

		$this->assertStringContainsString( '<h2>Find us</h2>', $html );
		$this->assertStringContainsString( 'Kastanienallee 48', $html );
		$this->assertStringContainsString( 'https://www.openstreetmap.org/export/embed.html?', $html );
		$this->assertStringContainsString( 'marker=52.538456%2C13.409564', $html );
		$this->assertStringContainsString( '#map=16/52.538456/13.409564', $html );
		$this->assertStringContainsString( '© OpenStreetMap contributors', $html );
		$this->assertStringContainsString( 'vendor/cms/theme/map.css', $html );
	}


	public function testMapRejectsInvalidCoordinates()
	{
		$page = ( new Page() )->forceFill( ['lang' => 'en'] );
		$data = (object) [
			'location' => (object) ['latitude' => 91, 'longitude' => 13.409564, 'zoom' => 16],
		];

		$html = view( 'cms::map', compact( 'data', 'page' ) )->render();

		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( 'openstreetmap.org', $html );
	}


	public function testContactRendersConfiguredFields()
	{
		$page = ( new \Aimeos\Cms\Models\Page() )->forceFill( ['id' => 'page-id', 'lang' => 'en'] );
		$data = (object) [
			'id' => 'contact-id',
			'title' => 'Contact us',
			'mandatory' => ['company', 'telephone'],
			'optional' => ['email', 'Account reference'],
		];

		$html = Blade::render(
			"@include('cms::contact', ['data' => \$data, 'page' => \$page])\n@stack('foot')",
			compact( 'data', 'page' ),
			true,
		);

		$this->assertStringContainsString( 'action="http://localhost/cmsapi/contact"', $html );
		$this->assertStringContainsString( 'pico.grid.min.css', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]+name="company"[^>]+required[^>]*>/', $html );
		$this->assertMatchesRegularExpression( '/type="tel"\s+name="telephone"/', $html );
		$this->assertMatchesRegularExpression( '/type="email"\s+name="email"/', $html );
		$this->assertStringContainsString( 'name="' . ContactRequest::key( 'Account reference' ) . '"', $html );
		$this->assertStringContainsString( '>Account Reference</label>', $html );
		$this->assertSame( 1, preg_match( '/<input[^>]+name="email"[^>]*>/', $html, $email ) );
		$this->assertStringNotContainsString( 'required', $email[0] );
		$this->assertStringNotContainsString( 'name="name"', $html );
		$this->assertMatchesRegularExpression( '/name="signature" value="[a-f0-9]{64}"/', $html );
	}


	public function testContactRendersLegacyFieldsAsMandatory()
	{
		$page = ( new \Aimeos\Cms\Models\Page() )->forceFill( ['id' => 'page-id', 'lang' => 'en'] );
		$data = (object) ['id' => 'contact-id', 'fields' => ['subject']];

		$html = view( 'cms::contact', compact( 'data', 'page' ) )->render();

		$this->assertMatchesRegularExpression( '/<input[^>]+name="subject"[^>]+required[^>]*>/', $html );
	}


	public function testCardsImagesLinkToOptionalUrl()
	{
		$page = ( new \Aimeos\Cms\Models\Page() )->forceFill( ['lang' => 'en'] );
		$file = (object) [
			'id' => 'image',
			'name' => 'Card image',
			'path' => 'https://example.com/card.jpg',
			'previews' => [],
		];
		$data = (object) ['cards' => [
			(object) ['title' => 'Linked', 'file' => (object) ['id' => 'image'], 'url' => '/target'],
			(object) ['title' => 'Unlinked', 'file' => (object) ['id' => 'image']],
			(object) ['title' => 'Unsafe', 'file' => (object) ['id' => 'image'], 'url' => 'javascript:alert(1)'],
		]];

		$html = view( 'cms::cards', ['data' => $data, 'files' => collect( ['image' => $file] ), 'page' => $page] )->render();

		$this->assertSame( 3, substr_count( $html, '<picture class="image"' ) );
		$this->assertSame( 1, substr_count( $html, '<a class="card-image"' ) );
		$this->assertMatchesRegularExpression( '#<a class="card-image" href="/target">\s*<picture class="image".*?</picture>\s*</a>#s', $html );
	}


	public function testCmsDataIncludesSharedElementFiles() : void
	{
		$page = ( new Page() )->forceFill( ['id' => 'page'] );
		$pageFile = ( new File() )->forceFill( ['id' => 'page-file'] );
		$elementFile = ( new File() )->forceFill( ['id' => 'element-file'] );
		$element = ( new Element() )->forceFill( [
			'id' => 'element',
			'type' => 'cards',
			'name' => 'Shared footer',
			'data' => ['cards' => []],
		] );

		$page->setRelation( 'files', collect( [$pageFile] ) );
		$element->setRelation( 'files', collect( [$elementFile] ) );

		$data = cmsdata( $page, $element );

		$this->assertSame( [$pageFile->id, $elementFile->id], $data['files']->keys()->all() );
		$this->assertSame( 'cards', $data['type'] );
	}


	public function testCardsWithoutTitleDoNotRenderEmptyHeading()
	{
		$page = ( new \Aimeos\Cms\Models\Page() )->forceFill( ['lang' => 'en'] );
		$data = (object) ['cards' => [(object) ['text' => '- [Contact](/contact)']]];

		$html = view( 'cms::cards', ['data' => $data, 'files' => collect(), 'page' => $page] )->render();

		$this->assertStringNotContainsString( '<h3', $html );
		$this->assertStringContainsString( '<a href="/contact">Contact</a>', $html );
	}


	public function testListItemUsesIndexedArticleImage() : void
	{
		$page = ( new Page() )->forceFill( [
			'content' => [[
				'type' => 'article',
				'files' => ['image'],
				'data' => ['text' => 'Article introduction'],
			]],
			'created_at' => Carbon::parse( '2026-08-23 12:00:00' ),
			'domain' => 'localhost',
			'lang' => 'en',
			'path' => 'article',
			'title' => 'Article',
		] );
		$file = (object) [
			'id' => 'image',
			'name' => 'Article image',
			'path' => 'https://example.com/article.jpg',
			'previews' => [],
		];
		$page->setRelation( 'files', collect( ['image' => $file] ) );

		$html = view( 'cms::list-item', ['item' => $page, 'layout' => 'list', 'page' => $page] )->render();

		$this->assertStringContainsString( '<picture', $html );
		$this->assertStringContainsString( 'https://example.com/article.jpg', $html );
	}


	public function testArticleRendersGalleryAndLegacyImageFields() : void
	{
		$page = ( new Page() )->forceFill( [
			'created_at' => Carbon::parse( '2026-08-23 12:00:00' ),
			'updated_at' => Carbon::parse( '2026-08-24 12:00:00' ),
			'id' => 'page',
			'lang' => 'en',
			'title' => 'Article',
		] );
		$file = (object) [
			'id' => 'image',
			'name' => 'Article image',
			'path' => 'https://example.com/article.jpg',
			'previews' => [],
		];
		$files = collect( ['image' => $file] );
		$fields = [
			'gallery' => ['files' => [(object) ['id' => 'image', 'type' => 'file']]],
			'legacy' => ['file' => (object) ['id' => 'image', 'type' => 'file']],
		];

		foreach( $fields as $name => $field )
		{
			$data = (object) ( $field + ['text' => 'Article introduction'] );
			$html = view( 'cms::article', compact( 'data', 'files', 'page' ) )->render();

			$this->assertStringContainsString( '<picture class="cover"', $html, $name );
			$this->assertStringContainsString( 'https://example.com/article.jpg', $html, $name );
			$this->assertStringContainsString( '"image":', $html, $name );
		}
	}


	public function testRegisterPricingIdentities()
	{
		$fields = Schema::get( 'cms' )['content']['pricing']['fields'];
		$items = $fields['items'];
		$prices = $items['item']['prices'];
		$price = $prices['item'];

		$this->assertSame( 'id', $items['identity'] );
		$this->assertSame( 'id', $prices['identity'] );
		$this->assertSame( 5, $prices['max'] );
		$this->assertSame( 'autocomplete', $items['item']['access']['type'] );
		$this->assertSame( 'query{access(term:_term_,first:50)}', $items['item']['access']['query'] );
		$this->assertArrayNotHasKey( 'required', $items['item']['access'] );
		$this->assertArrayNotHasKey( 'min', $price['reference'] );
		$this->assertSame( 'string', $price['label']['type'] );
		$this->assertSame( 'number', $price['amount']['type'] );
		$this->assertSame( 2, $price['amount']['precision'] );
		$this->assertSame( 0.01, $price['amount']['step'] );
		$this->assertSame( '^[A-Z]{3}$', $price['currency']['pattern'] );
		$this->assertTrue( $price['currency']['uppercase'] );
		$this->assertSame( 'Price unit', $price['unit']['label'] );
		$this->assertSame( 'Target page or link', $items['item']['url']['label'] );
		$this->assertArrayNotHasKey( 'success', $items['item'] );
		$this->assertArrayNotHasKey( 'id', $items['item'] );
		$this->assertArrayNotHasKey( 'id', $prices['item'] );
	}


	public function testRegisterPricingIdentitiesAreGenerated()
	{
		$content = Validation::page( ['content' => [[
			'type' => 'pricing',
			'data' => ['items' => [[
				'name' => 'Professional',
				'prices' => [['reference' => 'price-1']],
			]]],
		]]] )['content'];

		$this->assertMatchesRegularExpression( '/^[A-Za-z][A-Za-z0-9_-]{5}$/', $content[0]->data->items[0]->id );
		$this->assertMatchesRegularExpression( '/^[A-Za-z][A-Za-z0-9_-]{5}$/', $content[0]->data->items[0]->prices[0]->id );
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
		$template = '@text($text){{-- no-break-tags --}}@text($suffix)';

		$this->assertEquals( "one\ntwo!", Blade::render( $template, ['text' => "one\ntwo", 'suffix' => '!'], true ) );
		$this->assertEquals( "one &amp; two\n<strong>three</strong>!", Blade::render( $template, ['text' => "one & two\n**three**", 'suffix' => '!'], true ) );
	}


	public function testLayoutTypeDefaultsToPage(): void
	{
		$paths = glob( dirname( __DIR__, 2 ) . '/themes/*/views/layouts/main.blade.php' ) ?: [];
		$paths[] = dirname( __DIR__ ) . '/views/layouts/main.blade.php';

		foreach( $paths as $path ) {
			$this->assertStringContainsString(
				"type-{{ cms(\$page, 'type') ?: 'page' }}",
				(string) file_get_contents( $path ),
				$path
			);
		}

		$html = Blade::render(
			"<body class=\"type-{{ cms(\$page, 'type') ?: 'page' }}\"></body>",
			['page' => (object) ['type' => '']],
			true
		);

		$this->assertSame( '<body class="type-page"></body>', $html );
	}


	public function testLoginLinkIsOptionalInAllLayouts(): void
	{
		$paths = glob( dirname( __DIR__, 2 ) . '/themes/*/views/layouts/main.blade.php' ) ?: [];
		$paths[] = dirname( __DIR__ ) . '/views/layouts/main.blade.php';

		foreach( $paths as $path )
		{
			$view = (string) file_get_contents( $path );
			$start = strpos( $view, "@if(Route::has('login'))" );

			$this->assertIsInt( $start, $path );

			$end = strpos( $view, '@endif', $start );

			$this->assertIsInt( $end, $path );

			$block = substr( $view, $start, $end - $start );

			$this->assertStringContainsString( '<li class="login">', $block, $path );
			$this->assertStringContainsString( 'href="{{ route(\'login\') }}"', $block, $path );
			$this->assertStringContainsString( 'aria-label="{{ __(\'Login\') }}"', $block, $path );
			$this->assertStringContainsString( '<svg', $block, $path );
			$this->assertSame( 1, substr_count( $view, "Route::has('login')" ), $path );
			$this->assertSame( 1, substr_count( $view, "route('login')" ), $path );
		}
	}


	public function testMarkdownDirectiveTrimsListBreaks(): void
	{
		$template = '<div class="text">@markdown($text)</div>';

		$this->assertEquals( '<div class="text"><ul><li>one</li><li>two</li></ul></div>', Blade::render( $template, ['text' => "- one\n- two"], true ) );
	}


	public function testMarkdownDirectiveTrimsOuterBreaks()
	{
		$template = '<div class="text">@markdown($text)</div><div class="text">@markdown($text)</div>';

		$this->assertEquals( '<div class="text"><p>one</p></div><div class="text"><p>one</p></div>', Blade::render( $template, ['text' => 'one'], true ) );
	}


	public function testTextClassNodesAreInline()
	{
		foreach( glob( dirname( __DIR__ ) . '/views/*.blade.php' ) ?: [] as $path ) {
			$view = file_get_contents( $path );

			preg_match_all( '/<(?<tag>[a-z][a-z0-9-]*)\b[^>]*class="(?:[^"]*\s)?cms-text(?:\s[^"]*)?"[^>]*>.*?<\/\k<tag>>/s', $view, $matches );

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
