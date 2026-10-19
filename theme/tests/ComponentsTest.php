<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Support\Facades\Blade;


class ComponentsTest extends CoreTestAbstract
{
    public function testBrand()
    {
        $page = $this->node( 'page-id', 'Page', 2, 3 );
        $root = $this->node( 'root-id', 'Root', 1, 4 );
        $page->setRelation( 'ancestors', collect( [$root] ) );

        $html = $this->squish( Blade::render( '<x-cms::brand :page="$page" :nav="$nav" class="contrast" />', ['page' => $page, 'nav' => $this->nav( ancestors: [$root] )] ) );

        $this->assertMatchesRegularExpression( '#<a href="[^"]*/root" title="Root" aria-label="Root" class="contrast">\s*Root\s*</a>#', $html );
        $this->assertStringNotContainsString( '<img', $html );
    }


    public function testBrandLogo()
    {
        $page = $this->node( 'page-id', 'Page', 1, 2 )->forceFill( ['config' => (object) [
            'logo' => (object) ['type' => 'logo', 'files' => ['logo-id'], 'data' => (object) ['file' => (object) ['type' => 'file', 'id' => 'logo-id']]],
            'logo-alternative' => (object) ['type' => 'logo-alternative', 'files' => ['alt-id'], 'data' => (object) ['file' => (object) ['type' => 'file', 'id' => 'alt-id']]],
        ]] );
        $page->setRelation( 'ancestors', collect() );
        $page->setRelation( 'files', collect( [
            'logo-id' => $this->file()->forceFill( ['id' => 'logo-id', 'path' => 'cms/logo.svg'] ),
            'alt-id' => $this->file()->forceFill( ['id' => 'alt-id', 'path' => 'cms/alt.svg'] ),
        ] ) );

        $html = $this->squish( Blade::render( '<x-cms::brand :page="$page" :nav="$nav" name="Brand" />', ['page' => $page, 'nav' => $this->nav()] ) );
        $this->assertMatchesRegularExpression( '#<img src="[^"]*cms/logo.svg" alt="Brand">#', $html );
        $this->assertStringContainsString( 'title="Brand"', $html );

        $html = $this->squish( Blade::render( '<x-cms::brand :page="$page" :nav="$nav" alternative />', ['page' => $page, 'nav' => $this->nav()] ) );
        $this->assertStringContainsString( 'cms/alt.svg', $html );
    }


    public function testBreadcrumb()
    {
        $root = $this->node( 'root-id', 'Root', 1, 6 );
        $parent = $this->node( 'parent-id', 'Parent', 2, 5 );
        $page = $this->node( 'page-id', 'Page', 3, 4 );

        $html = $this->squish( Blade::render( '<x-cms::breadcrumb :page="$page" :nav="$nav" />', ['page' => $page, 'nav' => $this->nav( ancestors: [$root, $parent] )] ) );

        $this->assertStringContainsString( '<nav class="breadcrumb" aria-label="Breadcrumb navigation">', $html );
        $this->assertMatchesRegularExpression( '#<a role="button" href="[^"]*/parent">Parent</a>#', $html );
        $this->assertStringContainsString( '<li>Page</li>', $html );
        $this->assertStringNotContainsString( 'Root', $html );
    }


    public function testBreadcrumbTopLevel()
    {
        $html = $this->squish( Blade::render( '<x-cms::breadcrumb :page="$page" :nav="$nav" />', [
            'page' => $this->node( 'page-id', 'Page', 2, 3 ),
            'nav' => $this->nav( ancestors: [$this->node( 'root-id', 'Root', 1, 4 )] ),
        ] ) );

        $this->assertSame( '', trim( $html ) );
    }


    public function testLanguages()
    {
        $variants = [(object) ['lang' => 'de', 'url' => '/de'], (object) ['lang' => 'en', 'url' => '/en']];
        $html = $this->squish( Blade::render( '<x-cms::languages :page="$page" :nav="$nav" />', ['page' => $this->page(), 'nav' => $this->nav( variants: $variants )] ) );

        $this->assertStringContainsString( '<summary aria-label="Language">EN</summary>', $html );
        $this->assertStringContainsString( '<a href="/de" hreflang="de" lang="de" class="">', $html );
        $this->assertStringContainsString( '<a href="/en" hreflang="en" lang="en" class="active">', $html );
    }


    public function testLanguagesSingle()
    {
        $html = $this->squish( Blade::render( '<x-cms::languages :page="$page" :nav="$nav" />', [
            'page' => $this->page(),
            'nav' => $this->nav( variants: [(object) ['lang' => 'en', 'url' => '/en']] ),
        ] ) );

        $this->assertSame( '', trim( $html ) );
    }


    public function testMenu()
    {
        [$page, $nav] = $this->menu();
        $html = $this->squish( Blade::render( '<x-cms::menu :page="$page" :nav="$nav" link-class="contrast" highlight="contact" />', compact( 'page', 'nav' ) ) );

        $this->assertStringContainsString( '<summary>Section</summary>', $html );
        $this->assertMatchesRegularExpression( '#href="[^"]*/child" class="active contrast" aria-current="page">#', $html );
        $this->assertMatchesRegularExpression( '#<li class="contact"> <a href="[^"]*/contact" class="contrast">#', $html );
        $this->assertDoesNotMatchRegularExpression( '#href="[^"]*/section"#', $html );
    }


    public function testMenuFlat()
    {
        [$page, $nav] = $this->menu();
        $html = $this->squish( Blade::render( '<x-cms::menu :page="$page" :nav="$nav" :dropdown="false" />', compact( 'page', 'nav' ) ) );

        $this->assertStringNotContainsString( '<details', $html );
        $this->assertStringNotContainsString( '/child', $html );
        $this->assertMatchesRegularExpression( '#href="[^"]*/section" class="active">#', $html );
    }


    public function testMenuParent()
    {
        [$page, $nav] = $this->menu();
        $html = $this->squish( Blade::render( '<x-cms::menu :page="$page" :nav="$nav" parent />', compact( 'page', 'nav' ) ) );

        $this->assertStringContainsString( '<summary class="active">Section</summary>', $html );
        $this->assertMatchesRegularExpression( '#href="[^"]*/section" class=""> Section#', $html );
    }


    public function testSearch()
    {
        $html = $this->squish( Blade::render( '<x-cms::search :page="$page" />', ['page' => $this->page()] ) );

        $this->assertStringContainsString( '<dialog id="modal-search" class="search"', $html );
        $this->assertStringContainsString( 'class="results"', $html );
    }


    public function testButtons()
    {
        $buttons = [
            (object) ['label' => 'Go', 'url' => 'https://example.com', 'url-rel' => 'nofollow'],
            (object) ['label' => 'None'],
        ];
        $html = Blade::render( '<x-cms::buttons :buttons="$buttons" class="center" />', ['buttons' => $buttons] );

        $this->assertStringContainsString( '<div class="actions center">', $html );
        $this->assertStringContainsString( '<a class="btn" href="https://example.com" rel="nofollow">Go</a>', $html );
        $this->assertStringNotContainsString( 'None', $html );
    }


    public function testButtonsEmpty()
    {
        $this->assertSame( '', trim( Blade::render( '<x-cms::buttons :buttons="[]" />' ) ) );
    }


    public function testCsp()
    {
        config( ['app.debug' => false, 'cms.theme.csp.frame-src' => 'https://www.youtube.com'] );
        $page = $this->page()->forceFill( ['config' => (object) ['javascript' => (object) ['type' => 'javascript', 'files' => [], 'data' => (object) ['text' => 'alert(1)']]]] );
        $page->setRelation( 'ancestors', collect() );
        $html = $this->squish( Blade::render( '<x-cms::csp :page="$page" />', ['page' => $page] ) );
        $hash = base64_encode( hash( 'sha256', 'alert(1)', true ) );

        $this->assertStringContainsString( '<meta http-equiv="Content-Security-Policy" content=" base-uri \'self\'; default-src \'self\';', $html );
        $this->assertStringContainsString( "frame-src 'self' https://www.youtube.com;", $html );
        $this->assertStringContainsString( "font-src 'self';", $html );
        $this->assertStringContainsString( "'sha256-$hash';", $html );
        $this->assertDoesNotMatchRegularExpression( "#style-src 'self' +'sha256-#", $html );
    }


    public function testElement()
    {
        $page = $this->page()->setRelation( 'files', collect() );
        $html = $this->squish( Blade::render( '@element($page, $item)', ['page' => $page, 'item' => (object) ['type' => 'notexist']] ) );

        $this->assertStringContainsString( '<!-- Invalid content element of type "notexist" -->', $html );
    }


    public function testElements()
    {
        $page = $this->page()->setRelation( 'files', collect() );
        $page->setRelation( 'elements', collect( ['element' => ( new \Aimeos\Cms\Models\Element() )->forceFill( ['id' => 'element', 'type' => 'notexist', 'data' => []] )->setRelation( 'files', collect() )] ) );

        $html = $this->squish( Blade::render( "@include('cms::elements', ['items' => \$items])", ['page' => $page, 'items' => [(object) ['id' => 'ref', 'type' => 'reference', 'refid' => 'element']]] ) );

        $this->assertStringContainsString( '<div id="ref" class="notexist"> <div class="container"> <!-- Invalid content element of type "notexist" -->', $html );
    }


    public function testCspDebug()
    {
        config( ['app.debug' => true] );

        $this->assertSame( '', trim( Blade::render( '<x-cms::csp :page="$page" />', ['page' => $this->page()] ) ) );
    }


    public function testHead()
    {
        config( ['app.debug' => false] );
        $file = $this->file()->forceFill( ['mime' => 'image/png', 'path' => 'cms/icon.png'] );
        $page = $this->page()->forceFill( [
            'title' => 'Title <x>',
            'meta' => (object) ['canonical' => (object) ['type' => 'canonical', 'files' => [], 'data' => (object) ['url' => 'https://example.com/page']]],
            'config' => (object) [
                'icon' => (object) ['type' => 'icon', 'files' => ['file-id'], 'data' => (object) ['file' => (object) ['type' => 'file', 'id' => 'file-id']]],
                'theme' => (object) ['type' => 'theme', 'files' => [], 'data' => (object) ['--pico-primary' => '#123456']],
            ],
        ] );
        $page->setRelation( 'files', collect( ['file-id' => $file] ) )->setRelation( 'ancestors', collect() );

        $html = $this->squish( Blade::render( '<x-cms::head :page="$page" />@stack(\'head\')', ['page' => $page] ) );
        $hash = base64_encode( hash( 'sha256', ':root:root{--pico-primary:#123456}', true ) );

        $this->assertStringStartsWith( '<meta charset="utf-8">', $html );
        $this->assertStringContainsString( '<meta http-equiv="Content-Security-Policy"', $html );
        $this->assertStringContainsString( '<meta name="theme-color" content="#FFFFFF">', $html );
        $this->assertStringContainsString( '<title>Title &lt;x&gt;</title>', $html );
        $this->assertSame( 1, substr_count( $html, '<link rel="canonical" href="https://example.com/page" />' ) );
        $this->assertStringContainsString( '<link rel="icon" type="image/png" href="/storage/cms/icon.png">', $html );
        $this->assertStringEndsWith( '<style>:root:root{--pico-primary:#123456}</style>', $html );
        $this->assertStringContainsString( "'sha256-$hash';", $html );
    }


    public function testImage()
    {
        $html = $this->render( '<x-cms::image :page="$page" :file="$file" />' );

        $this->assertStringContainsString( '<picture class="" itemscope itemprop="image" itemtype="http://schema.org/ImageObject">', $html );
        $this->assertStringContainsString( '<meta itemprop="representativeOfPage" content="false">', $html );
        $this->assertStringContainsString( 'loading="lazy"', $html );
        $this->assertStringContainsString( 'fetchpriority="low"', $html );
        $this->assertStringContainsString( 'sizes="100vw"', $html );
        $this->assertStringContainsString( 'a-1000.webp"', $html );
        $this->assertStringContainsString( 'srcset="/storage/cms/a-500.webp 500w,/storage/cms/a-1000.webp 1000w"', $html );
        $this->assertStringContainsString( 'alt="A &quot;x&quot;"', $html );
    }


    public function testImageAttributes()
    {
        $html = $this->render( '<x-cms::image :page="$page" :file="$file" main :class="[\'background\', \'zoom\']" sizes="50vw" id="hero" />' );

        $this->assertStringContainsString( '<picture class="background zoom" itemscope itemprop="image" itemtype="http://schema.org/ImageObject" id="hero">', $html );
        $this->assertStringContainsString( 'content="true"', $html );
        $this->assertStringContainsString( 'loading="eager"', $html );
        $this->assertStringContainsString( 'fetchpriority="high"', $html );
        $this->assertStringContainsString( 'sizes="50vw"', $html );
    }


    public function testImageNoPath()
    {
        $file = ( new \Aimeos\Cms\Models\File() )->forceFill( ['id' => 'file-id'] );
        $html = $this->render( '<x-cms::image :page="$page" :file="$file" />', $file );

        $this->assertStringContainsString( '<picture', $html );
        $this->assertStringNotContainsString( '<img', $html );
    }


    public function testPicPartial()
    {
        $page = $this->page();
        $file = $this->file();

        $this->assertSame(
            $this->render( '<x-cms::image :page="$page" :file="$file" main class="cover" sizes="50vw" />' ),
            view( 'cms::pic', ['page' => $page, 'file' => $file, 'main' => true, 'class' => 'cover', 'sizes' => '50vw'] )->render()
        );
    }


    public function testVideo()
    {
        $html = $this->render( '<x-cms::video :page="$page" :file="$file">Fallback</x-cms::video>' );

        $this->assertStringContainsString( 'preload="none" controls playsinline', $html );
        $this->assertStringContainsString( 'title=""', $html );
        $this->assertStringContainsString( 'src="', $html );
        $this->assertStringContainsString( 'poster="', $html );
        $this->assertStringContainsString( 'a-1000.webp"', $html );
        $this->assertStringContainsString( 'Fallback', $html );
        $this->assertStringNotContainsString( 'autoplay', $html );
    }


    public function testVideoAutoplay()
    {
        $file = ( new \Aimeos\Cms\Models\File() )->forceFill( ['id' => 'file-id', 'disk' => 'public', 'path' => 'cms/a.mp4'] );
        $html = $this->render( '<x-cms::video :page="$page" :file="$file" autoplay class="bg" />', $file );

        $this->assertStringContainsString( 'autoplay muted loop playsinline preload="metadata"', $html );
        $this->assertStringContainsString( 'class="bg"', $html );
        $this->assertStringNotContainsString( 'poster=', $html );
        $this->assertStringNotContainsString( 'controls', $html );
    }


    protected function file() : \Aimeos\Cms\Models\File
    {
        return ( new \Aimeos\Cms\Models\File() )->forceFill( [
            'id' => 'file-id', 'disk' => 'public', 'path' => 'cms/a.jpg', 'name' => 'A "x"',
            'previews' => [500 => 'cms/a-500.webp', 1000 => 'cms/a-1000.webp'],
        ] );
    }


    protected function menu() : array
    {
        $section = $this->node( 'section-id', 'Section', 2, 5 );
        $child = $this->node( 'child-id', 'Child', 3, 4 );
        $contact = $this->node( 'contact-id', 'Contact', 6, 7 );
        $section->setRelation( 'children', collect( [$child] ) );
        $contact->setRelation( 'children', collect() );

        return [$child, $this->nav( items: [$section, $contact] )];
    }


    protected function nav( array $items = [], array $ancestors = [], array $variants = [] ) : object
    {
        return new class( $items, $ancestors, $variants ) {
            public function __construct( private array $items, private array $ancestors, private array $variants ) {}
            public function ancestors() : \Illuminate\Support\Collection { return collect( $this->ancestors ); }
            public function items() : \Illuminate\Support\Collection { return collect( $this->items ); }
            public function variants() : \Illuminate\Support\Collection { return collect( $this->variants ); }
        };
    }


    protected function node( string $id, string $name, int $lft, int $rgt ) : \Aimeos\Cms\Models\PageView
    {
        return ( new \Aimeos\Cms\Models\PageView() )->forceFill( [
            'id' => $id, 'name' => $name, 'path' => strtolower( $name ), 'lang' => 'en',
            \Aimeos\Nestedset\NestedSet::LFT => $lft, \Aimeos\Nestedset\NestedSet::RGT => $rgt,
        ] );
    }


    protected function page() : \Aimeos\Cms\Models\PageView
    {
        return ( new \Aimeos\Cms\Models\PageView() )->forceFill( ['id' => 'page-id', 'lang' => 'en'] );
    }


    protected function squish( string $html ) : string
    {
        return trim( preg_replace( ['/\s+/', '/\s+>/'], [' ', '>'], $html ) );
    }


    protected function render( string $template, ?\Aimeos\Cms\Models\File $file = null ) : string
    {
        return Blade::render( $template, ['page' => $this->page(), 'file' => $file ?? $this->file()] );
    }
}
