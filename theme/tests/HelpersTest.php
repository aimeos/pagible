<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

class HelpersTest extends CoreTestAbstract
{
    public function testCms()
    {
        $page = new \Aimeos\Cms\Models\PageView( ['path' => 'blog'] );

        $this->assertEquals( 'blog', cms( $page, 'path' ) );
    }


    public function testCmsNested()
    {
        $item = (object) ['obj' => (object) ['arr' => ['key' => 'value']], 'str' => 'text'];

        $this->assertEquals( 'value', cms( $item, 'obj.arr.key' ) );
        $this->assertEquals( 'def', cms( $item, 'obj.arr.none', 'def' ) );
        $this->assertEquals( 'def', cms( $item, 'str.none', 'def' ) );
    }


    public function testCmsAssetKeepsPublicAndRemoteFileUrls()
    {
        $page = ( new \Aimeos\Cms\Models\PageView() )->forceFill( ['id' => 'page-id'] );
        $public = ( new \Aimeos\Cms\Models\File() )->forceFill( [
            'id' => 'file-id', 'disk' => 'public', 'path' => 'cms/test/public.pdf',
            'previews' => [500 => 'cms/test/public.webp'],
        ] );
        $remote = ( new \Aimeos\Cms\Models\File() )->forceFill( [
            'id' => 'remote-id', 'disk' => 'public', 'path' => 'https://example.com/file.pdf',
        ] );

        $this->assertEquals( '/storage/cms/test/public.pdf', cmsasset( $page, $public ) );
        $this->assertEquals( '/storage/cms/test/public.webp', cmsasset( $page, $public, 500 ) );
        $this->assertEquals( '/storage/cms/test/public.webp', cmsasset( $page, $public, 'cms/test/public.webp' ) );
        $this->assertEquals( 'https://example.com/file.pdf', cmsasset( $page, $remote ) );
    }


    public function testCmsconfig()
    {
        $root = new \Aimeos\Cms\Models\PageView();
        $root->setRawAttributes( ['config' => json_encode( [
            'website' => ['data' => ['title' => 'Root website']],
            'restaurant' => ['data' => ['name' => 'Root restaurant']],
        ] )] );

        $parent = new \Aimeos\Cms\Models\PageView();
        $parent->setRawAttributes( ['config' => json_encode( [
            'website' => ['data' => ['title' => 'Parent website']],
        ] )] );

        $page = new \Aimeos\Cms\Models\PageView();
        $page->setRawAttributes( ['config' => json_encode( [
            'website' => ['data' => ['title' => '   ']],
        ] )] );
        $page->setRelation( 'ancestors', collect( [$root, $parent] ) );

        $this->assertSame( 'Parent website', cmsconfig( $page, 'website.data.title' ) );
        $this->assertSame( 'Root restaurant', cmsconfig( $page, 'restaurant.data.name' ) );
        $this->assertSame( 'fallback', cmsconfig( $page, 'missing.data.value', 'fallback' ) );
    }


    public function testCmsAssetSignsPrivateFileForRestrictedRender()
    {
        config( ['cms.disks.private.ttl' => 120] );
        $page = ( new \Aimeos\Cms\Models\PageView() )->forceFill( ['id' => 'page-id'] );
        $file = ( new \Aimeos\Cms\Models\File() )->forceFill( [
            'id' => 'file-id', 'disk' => 'private', 'path' => 'cms/test/private.pdf',
        ] );
        request()->attributes->set( 'cms.asset-token-page', (string) $page->id );

        $url = cmsasset( $page, $file );
        $query = [];
        parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

        $this->assertArrayHasKey( 'expires', $query );
        $this->assertArrayHasKey( 'signature', $query );
        $this->assertSame( \Aimeos\Cms\Tenancy::value(), $query['tenant'] ?? null );
        $this->assertGreaterThan( now()->timestamp, (int) $query['expires'] );
        $this->assertLessThanOrEqual( now()->addSeconds( 120 )->timestamp, (int) $query['expires'] );
    }


    public function testCmsAssetDoesNotSignPrivateFileOutsideRestrictedRender()
    {
        $page = ( new \Aimeos\Cms\Models\PageView() )->forceFill( ['id' => 'page-id'] );
        $file = ( new \Aimeos\Cms\Models\File() )->forceFill( [
            'id' => 'file-id', 'disk' => 'private', 'path' => 'cms/test/private.pdf',
        ] );

        $this->assertStringNotContainsString( 'signature=', cmsasset( $page, $file ) );

        request()->attributes->set( 'cms.asset-token-page', 'other-page' );

        $this->assertStringNotContainsString( 'signature=', cmsasset( $page, $file ) );
    }


    public function testCmstheme()
    {
        $page = new \Aimeos\Cms\Models\PageView();

        $this->assertStringStartsWith( 'http://localhost/vendor/cms/theme/hero.css', cmstheme( $page, 'hero.css' ) );
    }


    public function testCmsthemeWithTheme()
    {
        $page = new \Aimeos\Cms\Models\PageView( ['theme' => 'notexist'] );

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
}
