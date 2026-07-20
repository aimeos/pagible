<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class FileVersionMigrationTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testMovesFileTextFromDataToAux(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $file = File::firstOrFail();
        $page = Page::firstOrFail();
        $fileVersion = $file->latest()->firstOrFail();
        $pageVersion = $page->latest()->firstOrFail();

        $data = (array) $fileVersion->data;
        $data['description'] = ['en' => 'Legacy description'];
        $data['transcription'] = ['en' => 'Legacy transcription'];
        $aux = ['description' => ['en' => 'Canonical description'], 'keep' => 'value'];

        $db->table( 'cms_versions' )->where( 'id', $fileVersion->id )->update( [
            'data' => json_encode( $data ),
            'aux' => json_encode( $aux ),
        ] );

        $pageData = (array) $pageVersion->data;
        $pageData['description'] = ['en' => 'Page description'];
        $db->table( 'cms_versions' )->where( 'id', $pageVersion->id )->update( ['data' => json_encode( $pageData )] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_20_000000_move_file_text_to_aux.php';
        $db->flushQueryLog();
        $db->enableQueryLog();
        $migration->up();

        $updates = array_filter( $db->getQueryLog(), fn( $entry ) => str_starts_with( strtolower( ltrim( $entry['query'] ) ), 'update' ) );
        $this->assertCount( 1, $updates );
        $this->assertCount( 3, array_values( $updates )[0]['bindings'] );

        $stored = $db->table( 'cms_versions' )->where( 'id', $fileVersion->id )->first();
        $storedData = json_decode( $stored->data ?? '', true );
        $storedAux = json_decode( $stored->aux ?? '', true );

        $this->assertArrayNotHasKey( 'description', $storedData );
        $this->assertArrayNotHasKey( 'transcription', $storedData );
        $this->assertSame( ['en' => 'Canonical description'], $storedAux['description'] );
        $this->assertSame( ['en' => 'Legacy transcription'], $storedAux['transcription'] );
        $this->assertSame( 'value', $storedAux['keep'] );

        $storedPage = $db->table( 'cms_versions' )->where( 'id', $pageVersion->id )->first();
        $this->assertSame( ['en' => 'Page description'], json_decode( $storedPage->data ?? '', true )['description'] );

        $db->flushQueryLog();
        $migration->up();

        $updates = array_filter( $db->getQueryLog(), fn( $entry ) => str_starts_with( strtolower( ltrim( $entry['query'] ) ), 'update' ) );
        $this->assertCount( 0, $updates );
    }
}
