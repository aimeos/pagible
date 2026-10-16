<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class StructuredMigrationTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testSkipsTablesWithPageVariants(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $page = PageView::where( 'tag', 'root' )->firstOrFail();

        $meta = ['meta-tags' => ['id' => 'legacy', 'type' => 'meta-tags', 'group' => 'main', 'data' => ['description' => 'Page']]];
        $aux = ['meta' => ['type' => 'meta-tags', 'data' => ['description' => 'Version']], 'content' => []];

        $db->table( 'cms_page_variants' )->where( 'id', $page->variant_id )->update( ['meta' => json_encode( $meta )] );
        $db->table( 'cms_versions' )->where( 'id', $page->latest_id )->update( ['aux' => json_encode( $aux )] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_10_000000_normalize_page_meta_config.php';
        $migration->up();

        $stored = $db->table( 'cms_page_variants' )->where( 'id', $page->variant_id )->first();
        $version = $db->table( 'cms_versions' )->where( 'id', $page->latest_id )->first();

        $this->assertEquals( $meta, json_decode( $stored->meta ?? '', true ) );
        $this->assertEquals( $aux, json_decode( $version->aux ?? '', true ) );
    }
}
