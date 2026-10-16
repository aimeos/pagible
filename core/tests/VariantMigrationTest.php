<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class VariantMigrationTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testContinuesInterruptedMigration(): void
    {
        $name = config( 'cms.db', 'sqlite' );
        $db = DB::connection( $name );
        $schema = Schema::connection( $name );

        $variants = $db->table( 'cms_page_variants' )->orderBy( 'id' )->get()->all();
        $files = $db->table( 'cms_page_file' )->count();
        $elements = $db->table( 'cms_page_element' )->orderBy( 'variant_id' )->orderBy( 'element_id' )->get()->all();

        // interrupted after the new page/element table replaced the old one but before the indexes were added
        $schema->table( 'cms_page_element', function( $table ) use ( $db ) {
            // MySQL/MariaDB require an index for each foreign key and create one named like the constraint
            if( in_array( $db->getDriverName(), ['mysql', 'mariadb'] ) ) {
                $table->index( 'variant_id', 'cms_page_element_variant_fk' );
                $table->index( 'element_id', 'cms_page_element_element_id_fk' );
            }

            $table->dropUnique( ['variant_id', 'element_id'] );
            $table->dropIndex( ['element_id'] );
        } );
        $schema->rename( 'cms_page_element', 'cms_page_element_tmp' );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_10_06_000000_create_page_variants.php';
        $migration->up();
        $migration->up();

        $this->assertEquals( $variants, $db->table( 'cms_page_variants' )->orderBy( 'id' )->get()->all() );
        $this->assertEquals( $elements, $db->table( 'cms_page_element' )->orderBy( 'variant_id' )->orderBy( 'element_id' )->get()->all() );
        $this->assertSame( $files, $db->table( 'cms_page_file' )->count() );
        $this->assertFalse( $schema->hasTable( 'cms_page_element_tmp' ) );

        $indexes = array_column( $schema->getIndexes( 'cms_page_element' ), 'name' );
        $this->assertContains( 'cms_page_element_variant_id_element_id_unique', $indexes );
        $this->assertContains( 'cms_page_element_element_id_index', $indexes );
    }
}
