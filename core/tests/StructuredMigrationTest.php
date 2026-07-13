<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class StructuredMigrationTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testNormalizesPublishedAndVersionData(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $page = Page::where( 'tag', 'root' )->firstOrFail();

        $db->table( 'cms_pages' )->where( 'id', $page->id )->update( [
            'meta' => json_encode( [
                'meta-tags' => [
                    'id' => 'legacy',
                    'type' => 'meta-tags',
                    'group' => 'main',
                    'data' => ['description' => 'Page'],
                ],
            ] ),
            'config' => json_encode( [
                'logo' => ['file' => ['type' => 'file', 'id' => 'file-1']],
            ] ),
        ] );

        $postRelease = Page::where( 'tag', 'disabled' )->firstOrFail();
        $postReleaseMeta = [
            ['type' => 'meta-tags', 'data' => ['description' => 'Post-release demo']],
        ];

        $db->table( 'cms_pages' )->where( 'id', $postRelease->id )->update( [
            'meta' => json_encode( $postReleaseMeta ),
            'config' => json_encode( ['styles' => ['text' => 'main {}']] ),
        ] );

        $legacy = Page::whereNotIn( 'id', [$page->id, $postRelease->id] )->firstOrFail();
        $db->table( 'cms_pages' )->where( 'id', $legacy->id )->update( [
            'meta' => json_encode( ['meta-tags' => ['description' => 'Second page']] ),
        ] );

        $db->table( 'cms_versions' )->where( 'id', $page->latest_id )->update( [
            'aux' => json_encode( [
                'meta' => ['type' => 'meta-tags', 'data' => ['description' => 'Version']],
                'config' => ['styles' => ['text' => 'body {}']],
                'content' => [],
            ] ),
        ] );

        $other = $db->table( 'cms_versions' )->where( 'versionable_type', '!=', Page::class )->first();
        $this->assertNotNull( $other );
        $otherAux = ['meta' => ['meta-tags' => ['description' => 'Non-page version']]];
        $db->table( 'cms_versions' )->where( 'id', $other->id )->update( ['aux' => json_encode( $otherAux )] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_10_000000_normalize_page_meta_config.php';
        $db->flushQueryLog();
        $db->enableQueryLog();
        $migration->up();

        $updates = array_filter( $db->getQueryLog(), fn( $entry ) => str_starts_with( strtolower( ltrim( $entry['query'] ) ), 'update' ) );
        $pageUpdates = array_values( array_filter( $updates, fn( $entry ) => str_contains( $entry['query'], 'cms_pages' ) ) );
        $bindings = array_map( fn( $entry ) => count( $entry['bindings'] ), $pageUpdates );
        sort( $bindings );
        $this->assertCount( 3, $pageUpdates );
        $this->assertSame( [2, 2, 3], $bindings );
        $versionUpdates = array_filter( $updates, fn( $entry ) => str_contains( $entry['query'], 'cms_versions' ) );
        $this->assertNotEmpty( $versionUpdates );
        $this->assertSame( [2], array_values( array_unique( array_map( fn( $entry ) => count( $entry['bindings'] ), $versionUpdates ) ) ) );

        $stored = $db->table( 'cms_pages' )->where( 'id', $page->id )->first();
        $meta = json_decode( $stored->meta ?? '', true );
        $config = json_decode( $stored->config ?? '', true );

        $this->assertEqualsCanonicalizing( ['type', 'data', 'files'], array_keys( $meta['meta-tags'] ) );
        $this->assertArrayNotHasKey( 'id', $meta['meta-tags'] );
        $this->assertArrayNotHasKey( 'group', $meta['meta-tags'] );
        $this->assertSame( ['file-1'], $config['logo']['files'] );

        $version = $db->table( 'cms_versions' )->where( 'id', $page->latest_id )->first();
        $aux = json_decode( $version->aux ?? '', true );

        $this->assertSame( 'Version', $aux['meta']['meta-tags']['data']['description'] );
        $this->assertSame( [], $aux['meta']['meta-tags']['files'] );
        $this->assertSame( 'body {}', $aux['config']['styles']['data']['text'] );
        $this->assertSame( [], $aux['content'] );

        $postReleaseStored = $db->table( 'cms_pages' )->where( 'id', $postRelease->id )->first();

        $this->assertEquals( $postReleaseMeta, json_decode( $postReleaseStored->meta ?? '', true ) );
        $postReleaseConfig = json_decode( $postReleaseStored->config ?? '', true );
        $this->assertSame( 'main {}', $postReleaseConfig['styles']['data']['text'] );

        $legacyStored = $db->table( 'cms_pages' )->where( 'id', $legacy->id )->first();
        $legacyMeta = json_decode( $legacyStored->meta ?? '', true );
        $this->assertSame( 'Second page', $legacyMeta['meta-tags']['data']['description'] );

        $otherStored = $db->table( 'cms_versions' )->where( 'id', $other->id )->first();
        $this->assertEquals( $otherAux, json_decode( $otherStored->aux ?? '', true ) );

        $db->flushQueryLog();
        $migration->up();

        $updates = array_filter( $db->getQueryLog(), fn( $entry ) => str_starts_with( strtolower( ltrim( $entry['query'] ) ), 'update' ) );
        $this->assertCount( 0, $updates );
    }
}
