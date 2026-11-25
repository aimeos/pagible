<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Statamic;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CmsWithMigrations;
use Tests\ImportTestAbstract;


/**
 * Base class of the Statamic import tests with the tables of the Statamic Eloquent driver.
 */
abstract class TestAbstract extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'database.connections.statamic', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection( 'statamic' );

        $schema->create( 'entries', function( Blueprint $table ) {
            $table->string( 'id' )->primary();
            $table->string( 'site' );
            $table->string( 'origin_id' )->nullable();
            $table->boolean( 'published' )->default( true );
            $table->string( 'slug' )->nullable();
            $table->string( 'uri' )->nullable();
            $table->string( 'date' )->nullable();
            $table->integer( 'order' )->nullable();
            $table->string( 'collection' );
            $table->string( 'blueprint' )->nullable();
            $table->json( 'data' );
            $table->timestamps();
        } );

        $schema->create( 'collections', function( Blueprint $table ) {
            $table->id();
            $table->string( 'handle' );
            $table->string( 'title' )->default( '' );
            $table->json( 'settings' )->nullable();
            $table->timestamps();
        } );

        $schema->create( 'trees', function( Blueprint $table ) {
            $table->id();
            $table->string( 'handle' );
            $table->string( 'type' );
            $table->string( 'locale' )->nullable();
            $table->json( 'tree' )->nullable();
            $table->json( 'settings' )->nullable();
            $table->timestamps();
        } );

        $schema->create( 'sites', function( Blueprint $table ) {
            $table->id();
            $table->string( 'handle' );
            $table->string( 'name' )->default( '' );
            $table->string( 'url' )->default( '/' );
            $table->string( 'locale' )->default( '' );
            $table->string( 'lang' )->default( '' );
            $table->json( 'attributes' )->nullable();
            $table->integer( 'order' )->default( 0 );
            $table->timestamps();
        } );

        $schema->create( 'blueprints', function( Blueprint $table ) {
            $table->id();
            $table->string( 'namespace' )->nullable();
            $table->string( 'handle' );
            $table->json( 'data' );
            $table->timestamps();
        } );

        $schema->create( 'fieldsets', function( Blueprint $table ) {
            $table->id();
            $table->string( 'handle' );
            $table->json( 'data' );
            $table->timestamps();
        } );

        $this->site( 'en', 'en_US' );

        $schema->create( 'assets_meta', function( Blueprint $table ) {
            $table->id();
            $table->string( 'container' );
            $table->string( 'folder' )->default( '/' );
            $table->string( 'basename' )->default( '' );
            $table->string( 'filename' );
            $table->string( 'extension' );
            $table->string( 'path' );
            $table->json( 'meta' )->nullable();
            $table->timestamps();
        } );
    }


    /**
     * Inserts the entries with default values for the missing columns.
     *
     * @param array<int, array<string, mixed>> $entries Entries with "data" as array
     */
    protected function entries( array $entries ): void
    {
        DB::connection( 'statamic' )->table( 'entries' )->insert( array_map( fn( $entry ) => array_replace( [
            'site' => 'en', 'collection' => 'pages', 'published' => true, 'origin_id' => null, 'slug' => null, 'uri' => null,
            'date' => null, 'order' => null, 'updated_at' => null,
        ], $entry, ['data' => json_encode( $entry['data'] ?? [] )] ), $entries ) );
    }


    /**
     * Inserts the collections.
     *
     * @param array<string, array<string, mixed>> $collections Settings by handle
     * @param string|null $updated Modification date of the collections
     */
    protected function collections( array $collections, ?string $updated = null ): void
    {
        foreach( $collections as $handle => $settings ) {
            DB::connection( 'statamic' )->table( 'collections' )->insert( ['handle' => $handle, 'settings' => json_encode( $settings ), 'updated_at' => $updated] );
        }
    }


    /**
     * Inserts the tree of the collection.
     *
     * @param string $handle Collection handle
     * @param array<int, array<string, mixed>> $tree Tree nodes
     * @param string|null $locale Site handle of the tree
     */
    protected function tree( string $handle, array $tree, ?string $locale = 'en' ): void
    {
        DB::connection( 'statamic' )->table( 'trees' )->insert( ['handle' => $handle, 'type' => 'collection', 'locale' => $locale, 'tree' => json_encode( $tree )] );
    }


    /**
     * Inserts the site.
     *
     * @param string $handle Site handle
     * @param string $locale Locale of the site
     * @param string $url URL of the site
     * @param string $lang Language of the site, derived from the locale if empty
     */
    protected function site( string $handle, string $locale, string $url = '/', string $lang = '' ): void
    {
        $order = DB::connection( 'statamic' )->table( 'sites' )->count();
        DB::connection( 'statamic' )->table( 'sites' )->insert( ['handle' => $handle, 'locale' => $locale, 'url' => $url, 'lang' => $lang, 'order' => $order] );
    }


    /**
     * Inserts the blueprint of the collection.
     *
     * @param string $collection Collection handle
     * @param string $handle Blueprint handle
     * @param array<int|string, mixed> $fields Asset containers or field settings by field handle, imported fieldsets and field references without handle
     * @param array<string, mixed> $props Other blueprint properties like "order" and "hide"
     */
    protected function blueprint( string $collection, string $handle, array $fields, array $props = [] ): void
    {
        $fields = array_map( fn( $field, $config ) => is_int( $field ) ? $config : ['handle' => $field, 'field' => is_array( $config ) ? $config : ['type' => 'assets', 'container' => $config]], array_keys( $fields ), $fields );

        DB::connection( 'statamic' )->table( 'blueprints' )->insert( [
            'namespace' => 'collections.' . $collection, 'handle' => $handle,
            'data' => json_encode( $props + ['tabs' => ['main' => ['sections' => [['fields' => $fields]]]]] ),
        ] );
    }


    /**
     * Inserts the fieldset.
     *
     * @param string $handle Fieldset handle
     * @param array<int, array<string, mixed>> $fields Fields like {"handle": "body", "field": {...}} or imported fieldsets
     */
    protected function fieldset( string $handle, array $fields ): void
    {
        DB::connection( 'statamic' )->table( 'fieldsets' )->insert( ['handle' => $handle, 'data' => json_encode( ['title' => ucfirst( $handle ), 'fields' => $fields] )] );
    }


    /**
     * Inserts the asset meta data.
     *
     * @param string $container Asset container handle
     * @param string $path Path of the asset
     * @param array<string, mixed> $data Title and alt text of the asset
     * @param string|null $mime MIME type of the asset
     */
    protected function asset( string $container, string $path, array $data = [], ?string $mime = null ): void
    {
        DB::connection( 'statamic' )->table( 'assets_meta' )->insert( [
            'container' => $container, 'path' => $path, 'basename' => basename( $path ),
            'filename' => pathinfo( $path, PATHINFO_FILENAME ), 'extension' => pathinfo( $path, PATHINFO_EXTENSION ),
            'meta' => json_encode( ['data' => $data] + ( $mime !== null ? ['mime_type' => $mime] : [] ) ),
        ] );
    }
}
