<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

class HelpersTest extends CoreTestAbstract
{
    public function testCms()
    {
        $page = new \Aimeos\Cms\Models\Page( ['path' => 'blog'] );

        $this->assertEquals( 'blog', cms( $page, 'path' ) );
    }


    public function testCmsAsset()
    {
        $this->assertEquals( 'http://localhost/not/exists.js', cmsasset( 'not/exists.js' ) );
    }


    public function testCmsSrcset()
    {
        $this->assertEquals( '/storage/not/exists.jpg 1w', cmssrcset( [1 => 'not/exists.jpg'] ) );
    }


    public function testCmsUrl()
    {
        $this->assertEquals( 'data:ABCD', cmsurl( 'data:ABCD' ) );
        $this->assertEquals( '/storage/not/exists.jpg', cmsurl( 'not/exists.jpg' ) );
        $this->assertEquals( 'http://example.com/not/exists.jpg', cmsurl( 'http://example.com/not/exists.jpg' ) );
        $this->assertEquals( 'https://example.com/not/exists.jpg', cmsurl( 'https://example.com/not/exists.jpg' ) );
    }


    public function testCmstheme()
    {
        $page = new \Aimeos\Cms\Models\Page();

        $this->assertStringStartsWith( 'http://localhost/vendor/cms/theme/hero.css', cmstheme( $page, 'hero.css' ) );
    }


    public function testCmsthemeWithTheme()
    {
        $page = new \Aimeos\Cms\Models\Page( ['theme' => 'notexist'] );

        $this->assertStringStartsWith( 'http://localhost/vendor/cms/theme/hero.css', cmstheme( $page, 'hero.css' ) );
    }


    public function testCmsattr()
    {
        $this->assertEquals( 'Hello-World', cmsattr( 'Hello World' ) );
        $this->assertEquals( 'foo-bar', cmsattr( 'foo@bar' ) );
        $this->assertEquals( 'test123', cmsattr( 'test123' ) );
        $this->assertEquals( 'my-attr', cmsattr( 'my-attr' ) );
        $this->assertEquals( '', cmsattr( null ) );
    }


    public function testCmsjson()
    {
        $this->assertEquals( '"page title"', cmsjson( 'page title' ) );
        $this->assertEquals( '"O\'Brien & Co."', cmsjson( 'O\'Brien & Co.' ) );
        $this->assertEquals( '"\u003C/script\u003E"', cmsjson( '</script>' ) );
        $this->assertEquals( '"http://example.com/a/b"', cmsjson( 'http://example.com/a/b' ) );
        $this->assertEquals( '"Grüße"', cmsjson( 'Grüße' ) );
        $this->assertEquals( '{"name":"Tÿpe"}', cmsjson( ['name' => 'Tÿpe'] ) );
        $this->assertEquals( 'null', cmsjson( null ) );
    }


    public function testCmsviews()
    {
        $page = new \Aimeos\Cms\Models\Page();
        $item = (object) ['type' => 'heading'];

        $views = cmsviews( $page, $item );

        $this->assertCount( 2, $views );
        $this->assertEquals( 'cms::heading', $views[0] );
        $this->assertEquals( 'cms::invalid', $views[1] );
    }


    public function testCmsviewsWithTheme()
    {
        $page = new \Aimeos\Cms\Models\Page();
        $page->theme = 'mytheme';
        $item = (object) ['type' => 'mytheme::card'];

        $views = cmsviews( $page, $item );

        $this->assertEquals( ['mytheme::card', 'cms::card', 'cms::invalid'], $views );
    }


    public function testCmsviewsIgnoresPageThemeForNamespacedType()
    {
        $page = new \Aimeos\Cms\Models\Page();
        $page->theme = 'cms';
        $item = (object) ['type' => 'style::hero'];

        $views = cmsviews( $page, $item );

        $this->assertEquals( ['style::hero', 'cms::hero', 'cms::invalid'], $views );
    }


    public function testCmsviewsWithPageThemeOverride()
    {
        $page = new \Aimeos\Cms\Models\Page();
        $page->theme = 'mytheme';
        $item = (object) ['type' => 'hero'];

        $views = cmsviews( $page, $item );

        $this->assertEquals( ['mytheme::hero', 'cms::hero', 'cms::invalid'], $views );
    }


    public function testCmsviewsIncludesMissingPageThemeFallback()
    {
        $page = new \Aimeos\Cms\Models\Page();
        $page->theme = 'missingtheme';
        $item = (object) ['type' => 'hero'];

        $views = cmsviews( $page, $item );

        $this->assertEquals( ['missingtheme::hero', 'cms::hero', 'cms::invalid'], $views );
    }


    public function testCmsviewsNoType()
    {
        $page = new \Aimeos\Cms\Models\Page();

        $this->assertEquals( ['cms::invalid'], cmsviews( $page, (object) [] ) );
    }
}
