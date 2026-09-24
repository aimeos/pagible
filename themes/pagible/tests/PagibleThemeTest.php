<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\PagibleServiceProvider;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Validation;


class PagibleThemeTest extends ThemeTestAbstract
{
    public function testTextTrioSchema() : void
    {
        $schema = Schema::get( 'pagible' )['content']['pagible::text-trio'];

        $this->assertSame( 'Text Trio', $schema['label'] );
        $this->assertSame( 'content', $schema['group'] );
        $this->assertSame( ['leading', 'title', 'supporting'], array_keys( $schema['fields'] ) );
        $this->assertSame( ['string', 'string', 'string'], array_column( $schema['fields'], 'type' ) );
    }


    public function testTextTrioView() : void
    {
        $page = ( new Page() )->forceFill( ['id' => 'page-id', 'lang' => 'en', 'theme' => 'pagible'] );
        $content = Validation::page( ['content' => [[
            'id' => 'text-trio-id',
            'type' => 'pagible::text-trio',
            'group' => 'main',
            'data' => [
                'leading' => 'Leading copy',
                'title' => 'For editors',
                'supporting' => 'Supporting copy',
            ],
        ]]] )['content'];
        $data = $content[0]->data;

        $html = view( 'pagible::text-trio', ['id' => 'text-trio-id', 'data' => $data, 'page' => $page] )->render();

        $this->assertFileExists( dirname( __DIR__ ) . '/public/text-trio.css' );
        $this->assertStringContainsString( '<section class="text-trio">', $html );
        $this->assertStringContainsString( '<h2 class="text-trio-title">For editors</h2>', $html );
        $this->assertStringContainsString( '<p class="text-trio-leading">Leading copy</p>', $html );
        $this->assertStringContainsString( '<p class="text-trio-supporting">Supporting copy</p>', $html );
        $this->assertStringNotContainsString( 'type="radio"', $html );
        $this->assertStringNotContainsString( '<svg', $html );
    }


    public function testImageSizesMatchLayout() : void
    {
        $page = ( new Page() )->forceFill( ['id' => 'page-id', 'lang' => 'en', 'theme' => 'pagible'] );
        $files = collect( ['image-id' => (object) [
            'id' => 'image-id',
            'name' => 'Image',
            'path' => 'https://example.com/image.webp',
            'previews' => [],
        ]] );
        $sizes = 'sizes="(max-width: 575px) 90vw, (max-width: 1200px) calc(90vw - 3.6rem), 1022px"';

        $data = (object) ['file' => (object) ['id' => 'image-id']];
        $html = view( cmsviews( $page, (object) ['type' => 'image'] )[0], compact( 'data', 'files', 'page' ) )->render();
        $this->assertStringContainsString( $sizes, $html );

        $data = (object) ['files' => [(object) ['id' => 'image-id']], 'captions' => true];
        $html = view( cmsviews( $page, (object) ['type' => 'slideshow'] )[0], compact( 'data', 'files', 'page' ) )->render();
        $this->assertStringContainsString( $sizes, $html );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            PagibleServiceProvider::class,
        ] );
    }
}
