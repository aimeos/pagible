<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class HeroButtonsMigrationTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    public function testConvertsHeroButtons(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $element = Element::firstOrFail();

        $legacy = [
            'title' => 'Hero',
            'url' => '/start',
            'url-rel' => 'nofollow',
            'button' => 'Start',
            'url-alternative' => 'https://example.com',
            'button-alternative' => 'Learn more',
        ];
        $expected = [
            'title' => 'Hero',
            'buttons' => [
                ['label' => 'Start', 'url' => '/start', 'url-rel' => 'nofollow'],
                ['label' => 'Learn more', 'url' => 'https://example.com'],
            ],
        ];
        $content = [
            ['type' => 'heading', 'data' => ['title' => 'Unchanged', 'url' => '/keep']],
            ['type' => 'hero', 'data' => $legacy],
            ['type' => 'hero', 'data' => ['title' => 'Label only', 'button' => 'Orphan']],
        ];

        $db->table( 'cms_pages' )->where( 'id', $page->id )->update( ['content' => json_encode( $content )] );
        $db->table( 'cms_versions' )->where( 'id', $page->latest_id )->update( [
            'aux' => json_encode( ['content' => $content, 'meta' => [], 'config' => []] ),
        ] );
        $db->table( 'cms_elements' )->where( 'id', $element->id )->update( [
            'type' => 'hero',
            'data' => json_encode( $legacy ),
        ] );
        $db->table( 'cms_versions' )->where( 'id', $element->latest_id )->update( [
            'data' => json_encode( ['type' => 'hero', 'data' => $legacy] ),
        ] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_10_03_000000_convert_hero_buttons.php';
        $migration->up();

        $stored = json_decode( $db->table( 'cms_pages' )->where( 'id', $page->id )->value( 'content' ), true );

        $this->assertSame( ['title' => 'Unchanged', 'url' => '/keep'], $stored[0]['data'] );
        $this->assertSame( $expected, $stored[1]['data'] );
        $this->assertSame( ['title' => 'Label only'], $stored[2]['data'] );

        $aux = json_decode( $db->table( 'cms_versions' )->where( 'id', $page->latest_id )->value( 'aux' ), true );
        $this->assertSame( $expected, $aux['content'][1]['data'] );

        $data = json_decode( $db->table( 'cms_elements' )->where( 'id', $element->id )->value( 'data' ), true );
        $this->assertSame( $expected, $data );

        $version = json_decode( $db->table( 'cms_versions' )->where( 'id', $element->latest_id )->value( 'data' ), true );
        $this->assertSame( $expected, $version['data'] );

        $migration->up();

        $data = json_decode( $db->table( 'cms_elements' )->where( 'id', $element->id )->value( 'data' ), true );
        $this->assertSame( $expected, $data );
    }
}
