<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Commands\DrupalImport;
use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Drupal7;
use Aimeos\Cms\Import\Drupal7Db;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class Drupal7ImportTest extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'database.connections.drupal', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection( 'drupal' );

        $schema->create( 'node', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'type' );
            $table->string( 'language' )->default( 'und' );
            $table->string( 'title' );
            $table->unsignedInteger( 'uid' )->default( 1 );
            $table->integer( 'status' )->default( 1 );
            $table->unsignedInteger( 'created' )->default( 1700000000 );
            $table->unsignedInteger( 'changed' )->default( 1700000000 );
            $table->integer( 'promote' )->default( 0 );
            $table->integer( 'sticky' )->default( 0 );
            $table->unsignedInteger( 'tnid' )->default( 0 );
        } );

        $schema->create( 'field_config', function( Blueprint $table ) {
            $table->increments( 'id' );
            $table->string( 'field_name' );
            $table->string( 'type' );
            $table->integer( 'cardinality' )->default( 1 );
            $table->binary( 'data' );
            $table->integer( 'deleted' )->default( 0 );
        } );

        $schema->create( 'field_config_instance', function( Blueprint $table ) {
            $table->increments( 'id' );
            $table->string( 'field_name' );
            $table->string( 'entity_type' );
            $table->string( 'bundle' );
            $table->binary( 'data' );
            $table->integer( 'deleted' )->default( 0 );
        } );

        $schema->create( 'variable', function( Blueprint $table ) {
            $table->string( 'name' );
            $table->binary( 'value' );
        } );

        $schema->create( 'url_alias', function( Blueprint $table ) {
            $table->increments( 'pid' );
            $table->string( 'source' );
            $table->string( 'alias' );
            $table->string( 'language' )->default( 'und' );
        } );

        $schema->create( 'file_managed', function( Blueprint $table ) {
            $table->increments( 'fid' );
            $table->string( 'filename' );
            $table->string( 'uri' );
            $table->string( 'filemime' );
            $table->integer( 'status' )->default( 1 );
        } );

        $this->field( 'body', 'text_with_summary', ['value', 'summary', 'format'] );

        $db = DB::connection( 'drupal' );

        $db->table( 'variable' )->insert( [
            ['name' => 'language_default', 'value' => serialize( (object) ['language' => 'en', 'name' => 'English'] )],
            ['name' => 'site_name', 'value' => serialize( 'Example' )],
            ['name' => 'site_frontpage', 'value' => serialize( 'node' )],
        ] );
        $db->table( 'node' )->insert( ['nid' => 1, 'vid' => 1, 'type' => 'page', 'title' => 'About'] );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 1, ['value' => '<h2>Welcome</h2><p>Hello</p>', 'summary' => '', 'format' => 'filtered_html'] ) );
        $db->table( 'url_alias' )->insert( ['source' => 'node/1', 'alias' => 'about'] );

        $app = $this->app ?? throw new \RuntimeException( 'Application not initialized' );
        Artisan::registerCommand( $app->make( DrupalImport::class ) );
    }


    /**
     * Creates the configuration and data table of a Drupal 7 field.
     *
     * @param array<int, string> $columns Column names without field name prefix
     * @param array<string, mixed> $settings Field settings
     * @param array<string, mixed> $instance Field instance data
     */
    protected function field( string $name, string $type, array $columns, array $settings = [], array $instance = [],
        string $bundle = 'page', string $entity = 'node' ): void
    {
        $db = DB::connection( 'drupal' );

        if( !Schema::connection( 'drupal' )->hasTable( 'field_data_' . $name ) )
        {
            $db->table( 'field_config' )->insert( ['field_name' => $name, 'type' => $type, 'data' => serialize( ['settings' => $settings] )] );

            Schema::connection( 'drupal' )->create( 'field_data_' . $name, function( Blueprint $table ) use ( $name, $columns ) {
                $table->string( 'entity_type' );
                $table->string( 'bundle' );
                $table->integer( 'deleted' )->default( 0 );
                $table->unsignedInteger( 'entity_id' );
                $table->unsignedInteger( 'revision_id' );
                $table->string( 'language' )->default( 'und' );
                $table->unsignedInteger( 'delta' )->default( 0 );

                foreach( $columns as $column ) {
                    $table->text( $name . '_' . $column )->nullable();
                }
            } );
        }

        $db->table( 'field_config_instance' )->insert( [
            'field_name' => $name, 'entity_type' => $entity, 'bundle' => $bundle, 'data' => serialize( $instance + ['label' => ucfirst( $name )] ),
        ] );
    }


    /**
     * Returns a row of a field table.
     *
     * @param array<string, mixed> $values Values by column name without field name prefix
     * @return array<string, mixed>
     */
    protected function row( string $name, int $id, array $values, string $language = 'und', int $delta = 0,
        string $bundle = 'page', string $entity = 'node' ): array
    {
        $row = ['entity_type' => $entity, 'bundle' => $bundle, 'entity_id' => $id, 'revision_id' => $id, 'language' => $language, 'delta' => $delta];

        foreach( $values as $column => $value ) {
            $row[$name . '_' . $column] = $value;
        }

        return $row;
    }


    /**
     * Returns the content of the page as JSON.
     */
    protected function content( PageView $page ): string
    {
        return (string) json_encode( $page->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }


    public function testImport(): void
    {
        DB::connection( 'drupal' )->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Draft', 'status' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Found 1 Drupal nodes', $output );
        $this->assertSame( 0, PageView::where( 'name', 'Draft' )->count() );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertSame( 'about', $page->path );
        $this->assertSame( 'en', $page->lang );
        $this->assertStringContainsString( 'Welcome', $this->content( $page ) );
        $this->assertStringContainsString( 'Hello', $this->content( $page ) );

        // second import updates the existing page
        DB::connection( 'drupal' )->table( 'field_data_body' )->update( ['body_value' => '<p>Changed</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'About' )->sole();

        $this->assertStringContainsString( 'Changed', $this->content( $page ) );
    }


    public function testImportSince(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1, 'changed' => strtotime( '2026-01-01' )] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1, 'changed' => strtotime( '2026-01-01' )] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'ueber-uns', 'language' => 'de'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $db->table( 'field_data_body' )->where( 'entity_id', 1 )->update( ['body_value' => '<p>Changed</p>'] );
        $db->table( 'node' )->insert( ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'New', 'changed' => strtotime( '2026-03-01' )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Imported: New', $output );
        $this->assertStringContainsString( 'Hello', $this->content( PageView::where( 'name', 'About' )->firstOrFail() ) );

        // changed translation nodes render their source node again
        $db->table( 'node' )->where( 'nid', 2 )->update( ['changed' => strtotime( '2026-03-01' )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01'] ) );

        $this->assertStringContainsString( 'Changed', $this->content( PageView::where( 'name', 'About' )->firstOrFail() ) );

        // changed translations of the Entity Translation module render their node again
        $this->tables( 'entity_translation' );
        $db->table( 'entity_translation' )->insert( ['entity_type' => 'node', 'entity_id' => 1, 'language' => 'fr', 'changed' => strtotime( '2026-05-01' )] );
        $db->table( 'field_data_body' )->where( 'entity_id', 1 )->update( ['body_value' => '<p>Again</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-04-01'] ) );

        $this->assertStringContainsString( 'Again', $this->content( PageView::where( 'name', 'About' )->firstOrFail() ) );
    }


    public function testImportFrontPage(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->where( 'name', 'site_frontpage' )->update( ['value' => serialize( 'node/2' )] );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // the front page is the translation node whose source node becomes the root page
        $this->assertSame( 'About', PageView::where( 'tag', 'root' )->firstOrFail()->name );
    }


    public function testImportNodeAccess(): void
    {
        $this->tables( 'node_access', 'role' );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'user_admin_role', 'value' => serialize( '4' )] );
        $db->table( 'role' )->insert( [
            ['rid' => 1, 'name' => 'anonymous user'],
            ['rid' => 2, 'name' => 'authenticated user'],
            ['rid' => 3, 'name' => 'Premium Member'],
            ['rid' => 4, 'name' => 'administrator'],
        ] );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Premium', 'language' => 'en', 'tnid' => 2],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Gold', 'language' => 'und', 'tnid' => 0],
            ['nid' => 4, 'vid' => 4, 'type' => 'page', 'title' => 'Prämie', 'language' => 'de', 'tnid' => 2],
        ] );
        $db->table( 'node_access' )->insert( [
            ['nid' => 1, 'gid' => 1, 'realm' => 'content_access_rid', 'grant_view' => 1],
            ['nid' => 1, 'gid' => 2, 'realm' => 'content_access_rid', 'grant_view' => 1],
            ['nid' => 2, 'gid' => 3, 'realm' => 'content_access_rid', 'grant_view' => 1],
            ['nid' => 2, 'gid' => 2, 'realm' => 'content_access_rid', 'grant_view' => 0],
            // grants of the translation node only apply to the translation
            ['nid' => 4, 'gid' => 1, 'realm' => 'content_access_rid', 'grant_view' => 1],
            ['nid' => 3, 'gid' => 3, 'realm' => 'nodeaccess_rid', 'grant_view' => 1],
        ] );

        Access::using( fn() => ['administrator', 'gold'] );

        try
        {
            $db->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--role' => ['premium_member:gold']] ) );
            $grants = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, ' from "node_access" where "nid" in' ) );
            $db->disableQueryLog();

            // only the used columns of the grants are fetched
            $this->assertCount( 1, $grants );
            $this->assertStringStartsWith( 'select "nid", "gid", "realm" from', (string) $grants->first() );

            $access = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->accessValues();

            $this->assertNull( $access( 'About' ) );
            $this->assertSame( ['administrator', 'gold'], $access( 'Premium' ) );
            $this->assertSame( ['administrator', 'gold'], $access( 'Gold' ) );
        }
        finally
        {
            Access::using( null );
        }
    }


    public function testImportNodeAccessAll(): void
    {
        $this->tables( 'node_access' );

        // without node access modules, the grant for everyone makes all nodes public
        DB::connection( 'drupal' )->table( 'node_access' )->insert( [
            ['nid' => 0, 'gid' => 0, 'realm' => 'all', 'grant_view' => 1],
            ['nid' => 1, 'gid' => 3, 'realm' => 'content_access_rid', 'grant_view' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertNull( PageView::where( 'name', 'About' )->firstOrFail()->accessValues() );
    }


    public function testImportNoDrupal(): void
    {
        Schema::connection( 'drupal' )->drop( 'field_config_instance' );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'only Drupal 7 and newer are supported', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
    }


    public function testImportMenu(): void
    {
        Schema::connection( 'drupal' )->create( 'menu_links', function( Blueprint $table ) {
            $table->increments( 'mlid' );
            $table->string( 'menu_name' );
            $table->unsignedInteger( 'plid' )->default( 0 );
            $table->string( 'link_path' );
            $table->string( 'link_title' );
            $table->integer( 'hidden' )->default( 0 );
            $table->integer( 'weight' )->default( 0 );
            $table->string( 'language' )->default( 'und' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team'],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Jobs'],
            ['nid' => 4, 'vid' => 4, 'type' => 'page', 'title' => 'Archive'],
        ] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/3', 'alias' => 'jobs'] );
        $db->table( 'menu_links' )->insert( [
            ['mlid' => 1, 'menu_name' => 'main-menu', 'plid' => 0, 'link_path' => 'node/1', 'link_title' => 'About us', 'language' => 'en'],
            ['mlid' => 2, 'menu_name' => 'main-menu', 'plid' => 1, 'link_path' => 'node/2', 'link_title' => 'Our team', 'language' => 'und'],
            ['mlid' => 3, 'menu_name' => 'main-menu', 'plid' => 1, 'link_path' => 'jobs', 'link_title' => 'Careers', 'language' => 'und'],
            ['mlid' => 4, 'menu_name' => 'main-menu', 'plid' => 0, 'link_path' => 'node/2', 'link_title' => 'Equipe', 'language' => 'fr'],
            ['mlid' => 5, 'menu_name' => 'main-menu', 'plid' => 0, 'link_path' => '<front>', 'link_title' => 'Home', 'language' => 'und'],
            ['mlid' => 6, 'menu_name' => 'main-menu', 'plid' => 1, 'link_path' => 'node/4', 'link_title' => 'Hidden', 'language' => 'und'],
        ] );
        $db->table( 'menu_links' )->where( 'mlid', 6 )->update( ['hidden' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $this->assertStringContainsString( 'Skipped 1 menu links of other languages than "en"', Artisan::output() );

        $about = PageView::where( 'title', 'About' )->firstOrFail();
        $team = PageView::where( 'title', 'Team' )->firstOrFail();
        $jobs = PageView::where( 'title', 'Jobs' )->firstOrFail();

        $this->assertSame( 'About us', $about->name );
        $this->assertSame( 'Our team', $team->name );
        $this->assertSame( 'Careers', $jobs->name );
        $this->assertSame( $about->id, $team->parent_id );
        $this->assertSame( $about->id, $jobs->parent_id );
        $this->assertNotSame( $about->id, PageView::where( 'title', 'Archive' )->firstOrFail()->parent_id );
    }


    public function testImportFields(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'taxonomy_vocabulary' );
        $schema->create( 'taxonomy_term_data', function( Blueprint $table ) {
            $table->increments( 'tid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'name' );
            $table->text( 'description' )->nullable();
            $table->string( 'format' )->nullable();
            $table->integer( 'weight' )->default( 0 );
        } );

        $this->field( 'field_count', 'number_integer', ['value'], [], ['display' => ['default' => ['weight' => 1, 'label' => 'inline']]] );
        $this->field( 'field_color', 'list_text', ['value'], ['allowed_values' => ['red' => 'Red', 'blue' => 'Blue']] );
        $this->field( 'field_flag', 'list_boolean', ['value'], ['allowed_values' => [0 => 'No', 1 => 'Yes']] );
        $this->field( 'field_date', 'datetime', ['value', 'value2'], ['todate' => 'optional', 'granularity' => ['year' => 'year', 'month' => 'month', 'day' => 'day']] );
        $this->field( 'field_link', 'link_field', ['url', 'title', 'attributes'], [], ['settings' => ['attributes' => ['target' => 'user', 'configurable_title' => 1, 'title' => 'Default']]] );
        $this->field( 'field_web', 'link_field', ['url', 'title', 'attributes'], ['attributes' => ['target' => 'default', 'title' => 'Field']] );
        $this->field( 'field_web', 'link_field', ['url', 'title', 'attributes'], [], ['settings' => ['attributes' => ['target' => '_blank', 'title' => 'Ext']]], 'article' );
        $this->field( 'field_mail', 'email', ['email'] );
        $this->field( 'field_tags', 'taxonomy_term_reference', ['tid'] );
        $this->field( 'field_ref', 'node_reference', ['nid'] );
        $this->field( 'field_hidden', 'text', ['value', 'format'], [], ['display' => ['default' => ['type' => 'hidden']]] );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team', 'language' => 'en', 'tnid' => 2],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Mannschaft', 'language' => 'de', 'tnid' => 2],
        ] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'team', 'language' => 'en'] );
        $db->table( 'taxonomy_vocabulary' )->insert( ['vid' => 1, 'machine_name' => 'tags'] );
        $db->table( 'taxonomy_term_data' )->insert( ['tid' => 7, 'vid' => 1, 'name' => 'Drupal'] );
        $db->table( 'field_data_field_count' )->insert( $this->row( 'field_count', 1, ['value' => 42] ) );
        $db->table( 'field_data_field_color' )->insert( $this->row( 'field_color', 1, ['value' => 'blue'] ) );
        $db->table( 'field_data_field_flag' )->insert( $this->row( 'field_flag', 1, ['value' => 1] ) );
        $db->table( 'field_data_field_date' )->insert( $this->row( 'field_date', 1, ['value' => '2024-05-01 00:00:00', 'value2' => '2024-05-03 00:00:00'] ) );
        $db->table( 'field_data_field_link' )->insert( [
            $this->row( 'field_link', 1, ['url' => 'node/3', 'title' => 'Meet the team', 'attributes' => serialize( ['target' => '_blank', 'title' => 'Our team'] )] ),
            $this->row( 'field_link', 1, ['url' => 'https://example.org', 'title' => 'Partner', 'attributes' => serialize( ['target' => 0, 'title' => ''] )], delta: 1 ),
        ] );
        $db->table( 'field_data_field_web' )->insert( $this->row( 'field_web', 1, ['url' => 'https://example.net', 'title' => 'Web'] ) );
        $db->table( 'field_data_field_mail' )->insert( $this->row( 'field_mail', 1, ['email' => 'info@example.com'] ) );
        $db->table( 'field_data_field_tags' )->insert( $this->row( 'field_tags', 1, ['tid' => 7] ) );
        $db->table( 'field_data_field_ref' )->insert( $this->row( 'field_ref', 1, ['nid' => 3] ) );
        $db->table( 'field_data_field_hidden' )->insert( $this->row( 'field_hidden', 1, ['value' => 'Secret'] ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );

        $this->assertStringContainsString( 'Field_count:</strong> 42', $content );
        $this->assertStringContainsString( 'Blue', $content );
        $this->assertStringContainsString( 'Yes', $content );
        $this->assertStringContainsString( '2024-05-01 – 2024-05-03<', $content );
        $this->assertStringContainsString( '/team\\" target=\\"_blank\\" title=\\"Our team\\" rel=\\"noreferrer noopener\\">Meet the team', $content );
        $this->assertStringContainsString( '<a href=\\"https://example.org\\" title=\\"Default\\">Partner', $content );
        $this->assertStringContainsString( '<a href=\\"https://example.net\\" title=\\"Field\\">Web', $content );
        $this->assertStringContainsString( 'info@example.com', $content );
        $this->assertStringContainsString( 'Team', $content );
        $this->assertStringNotContainsString( 'Secret', $content );
        $this->assertLessThan( strpos( $content, 'Field_count' ), strpos( $content, 'Hello' ) );
    }


    public function testImportContentTranslationLinksWithoutAlias(): void
    {
        Schema::connection( 'drupal' )->create( 'languages', function( Blueprint $table ) {
            $table->string( 'language' );
            $table->string( 'prefix' )->default( '' );
            $table->string( 'domain' )->default( '' );
            $table->integer( 'enabled' )->default( 1 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'languages' )->insert( [['language' => 'en', 'prefix' => ''], ['language' => 'de', 'prefix' => 'de']] );
        $db->table( 'variable' )->insert( ['name' => 'language_negotiation_language', 'value' => serialize( ['locale-url' => [], 'language-default' => []] )] );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1] );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 2, ['value' => '<p>Hallo <a href="/node/1">hier</a></p>', 'format' => 'filtered_html'] ) );
        $db->table( 'url_alias' )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // links to nodes without alias use the URL of the translation derived from its title
        $page = PageView::where( 'title', 'About' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'de/über-uns', $de->path );
        $this->assertStringContainsString( 'href=\\"/de/%C3%BCber-uns\\"', $this->content( $de ) );
    }


    public function testImportRemovesIframesNotAllowedByTextFormat(): void
    {
        Schema::connection( 'drupal' )->create( 'filter', function( Blueprint $table ) {
            $table->string( 'format' );
            $table->string( 'name' );
            $table->integer( 'status' );
            $table->binary( 'settings' )->nullable();
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'filter' )->insert( [
            ['format' => 'filtered_html', 'name' => 'filter_html', 'status' => 1, 'settings' => serialize( ['allowed_html' => '<p> <a> <em>'] )],
            ['format' => 'video_html', 'name' => 'filter_html', 'status' => 1, 'settings' => serialize( ['allowed_html' => '<p> <iframe>'] )],
        ] );
        $body = '<p>Before</p><iframe src="https://attacker.example/login"></iframe><p>After</p>';
        $db->table( 'field_data_body' )->update( ['body_value' => $body, 'body_format' => 'filtered_html'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );
        $this->assertStringNotContainsString( 'iframe', $content );
        $this->assertStringContainsString( 'After', $content );

        $db->table( 'field_data_body' )->update( ['body_format' => 'video_html'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'attacker.example', $this->content( PageView::where( 'title', 'About' )->firstOrFail() ) );
    }


    public function testImportContentTranslation(): void
    {
        Schema::connection( 'drupal' )->create( 'languages', function( Blueprint $table ) {
            $table->string( 'language' );
            $table->string( 'prefix' )->default( '' );
            $table->string( 'domain' )->default( '' );
            $table->integer( 'enabled' )->default( 1 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'languages' )->insert( [['language' => 'en', 'prefix' => ''], ['language' => 'de', 'prefix' => 'de']] );
        $db->table( 'variable' )->insert( ['name' => 'language_negotiation_language', 'value' => serialize( ['locale-url' => [], 'language-default' => []] )] );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Duplicate', 'language' => 'de', 'tnid' => 1],
        ] );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 2, ['value' => '<p>Hallo <a href="/de/node/2">hier</a></p>', 'format' => 'filtered_html'] ) );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'ueber-uns', 'language' => 'de'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Skipped Drupal translation node 3, node 1 already has a translation in "de"', $output );
        $this->assertStringContainsString( 'Found 1 Drupal node translations', $output );
        $this->assertSame( 0, PageView::where( 'title', 'Duplicate' )->count() );

        $page = PageView::where( 'title', 'About' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'de/ueber-uns', $de->path );
        $this->assertStringContainsString( 'Hallo', $this->content( $de ) );
        $this->assertStringContainsString( 'href=\\"/de/ueber-uns\\"', $this->content( $de ) );
        $this->assertStringNotContainsString( 'Hello', $this->content( $de ) );
        $this->assertStringContainsString( 'Hello', $this->content( $page ) );
        $this->assertStringNotContainsString( 'Hallo', $this->content( $page ) );
    }


    public function testImportEntityTranslation(): void
    {
        $this->tables( 'entity_translation' );

        $this->field( 'title_field', 'text', ['value', 'format'] );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en'] );
        $db->table( 'entity_translation' )->insert( [
            ['entity_type' => 'node', 'entity_id' => 1, 'language' => 'en'],
            ['entity_type' => 'node', 'entity_id' => 1, 'language' => 'fr'],
        ] );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 1, ['value' => '<p>Bonjour</p>', 'format' => 'filtered_html'], 'fr' ) );
        $db->table( 'field_data_title_field' )->insert( [
            $this->row( 'title_field', 1, ['value' => 'About us'], 'en' ),
            $this->row( 'title_field', 1, ['value' => 'À propos'], 'fr' ),
        ] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/1', 'alias' => 'a-propos', 'language' => 'fr'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'title', 'About us' )->firstOrFail();
        $fr = PageView::language( 'fr' )->findOrFail( $page->id );

        $this->assertSame( 'À propos', $fr->title );
        $this->assertSame( 'a-propos', $fr->path );
        $this->assertStringContainsString( 'Bonjour', $this->content( $fr ) );
        $this->assertStringNotContainsString( 'About us', $this->content( $page ) );
    }


    public function testImportMediaTags(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'filename' => 'photo.jpg', 'uri' => 'public://photo.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 2, 'filename' => 'doc.pdf', 'uri' => 'private://doc.pdf', 'filemime' => 'application/pdf'],
            ['fid' => 3, 'filename' => 'Talk', 'uri' => 'youtube://v/dQw4w9WgXcQ', 'filemime' => 'video/youtube'],
            ['fid' => 4, 'filename' => 'Demo', 'uri' => 'vimeo://v/123', 'filemime' => 'video/vimeo'],
            ['fid' => 5, 'filename' => 'Clip', 'uri' => 'oembed://https%3A//youtu.be/abcdefgh', 'filemime' => 'video/oembed'],
        ] );
        $db->table( 'field_data_body' )->update( ['body_value' => '<p>Intro</p><p>[[{"fid":"1","type":"media","attributes":{"alt":"Photo"}}]]</p>'
            . '<p>[[{"fid":"2","type":"media"}]] [[{"fid":"9"}]]</p><p>[[{"fid":"3","type":"media"}]]</p><p>[[{"fid":"5","type":"media"}]]</p>'] );
        $this->field( 'field_video', 'file', ['fid', 'display', 'description'] );
        $db->table( 'field_data_field_video' )->insert( $this->row( 'field_video', 1, ['fid' => 4, 'display' => 1] ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );

        $this->assertSame( 1, File::where( 'path', 'like', '%/sites/default/files/photo.jpg' )->count() );
        $this->assertStringContainsString( '"type":"image"', $content );
        $this->assertSame( 1, File::where( 'path', 'like', '%/system/files/doc.pdf' )->count() );
        $this->assertStringNotContainsString( '[[', $content );
        $this->assertStringNotContainsString( 'fid', $content );

        // remote videos of the Media module are embedded
        $this->assertStringContainsString( '<iframe src=\\"https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ\\" title=\\"Talk\\"', $content );
        $this->assertStringContainsString( '<iframe src=\\"https://www.youtube-nocookie.com/embed/abcdefgh\\" title=\\"Clip\\"', $content );
        $this->assertStringContainsString( '<iframe src=\\"https://player.vimeo.com/video/123\\"', $content );
        $this->assertSame( 0, File::where( 'path', 'like', '%youtube%' )->orWhere( 'path', 'like', '%vimeo%' )->count() );
    }


    public function testImportVideoFilterAndAlignment(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'filename' => 'left.jpg', 'uri' => 'public://left.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 2, 'filename' => 'right.jpg', 'uri' => 'public://right.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 3, 'filename' => 'center.jpg', 'uri' => 'public://center.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 4, 'filename' => 'float.jpg', 'uri' => 'public://float.jpg', 'filemime' => 'image/jpeg'],
        ] );
        $db->table( 'field_data_body' )->update( ['body_format' => 'full_html', 'body_value' => '<p>[video:https://www.youtube.com/watch?v=abcdefgh width:640 height:360 align:left]</p>'
            . '<p>[video: https://vimeo.com/123]</p><p>[video:https://example.com/clip.mp4]</p><p>[video:javascript:alert(1)]</p>'
            . '<p>[[{"fid":"1","type":"media","attributes":{"alt":"Left","class":"media-element file-default media-wysiwyg-align-left"}}]]</p><p>Next to <em>left</em></p>'
            . '<p>[[{"fid":"2","type":"media","fields":{"alignment":"right"},"attributes":{"alt":"Right"}}]]Next to right</p>'
            . '<p>[[{"fid":"3","type":"media","fields":{"alignment":"center"},"attributes":{"alt":"Center"}}]]</p><p>Below center</p>'
            . '<p>[[{"fid":"4","type":"media","attributes":{"alt":"Float","style":"float:right; width: 200px"}}]]</p><h2>Heading</h2>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $content = array_values( (array) json_decode( (string) json_encode( PageView::where( 'title', 'About' )->firstOrFail()->content ), true ) );
        $types = array_column( $content, 'type' );

        // YouTube and Vimeo videos of the Video Filter module are embedded, other videos are linked
        $this->assertSame( ['html', 'html', 'html', 'image-text', 'image-text', 'image', 'html', 'image', 'heading'], $types );
        $this->assertStringStartsWith( '<iframe src="https://www.youtube-nocookie.com/embed/abcdefgh" width="640" height="360" ', $content[0]['data']['text'] );
        $this->assertStringStartsWith( '<iframe src="https://player.vimeo.com/video/123" ', $content[1]['data']['text'] );
        $this->assertStringContainsString( '<a href="https://example.com/clip.mp4">https://example.com/clip.mp4</a>', $content[2]['data']['text'] );
        $this->assertStringContainsString( '[video:javascript:alert(1)]', $content[2]['data']['text'] );

        // images aligned left or right are placed next to the following text
        $this->assertSame( ['start', 'Next to *left*'], [$content[3]['data']['position'], $content[3]['data']['text']] );
        $this->assertSame( ['end', 'Next to right'], [$content[4]['data']['position'], $content[4]['data']['text']] );
        $this->assertStringContainsString( 'Below center', $content[6]['data']['text'] );
        $this->assertSame( 'Heading', $content[8]['data']['title'] );
    }


    public function testImportMediaField(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'filename' => 'photo.jpg', 'uri' => 'public://photo.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 2, 'filename' => 'doc.pdf', 'uri' => 'public://doc.pdf', 'filemime' => 'application/pdf'],
            ['fid' => 3, 'filename' => 'clip.mp4', 'uri' => 'public://clip.mp4', 'filemime' => 'video/mp4'],
            ['fid' => 4, 'filename' => 'talk.mp3', 'uri' => 'public://talk.mp3', 'filemime' => 'audio/mpeg'],
            ['fid' => 5, 'filename' => 'Talk', 'uri' => 'youtube://v/dQw4w9WgXcQ', 'filemime' => 'video/youtube'],
            ['fid' => 6, 'filename' => 'other.jpg', 'uri' => 'public://other.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 7, 'filename' => 'movie.mp4', 'uri' => 'public://movie.mp4', 'filemime' => 'video/mp4'],
        ] );
        $this->field( 'field_media', 'media', ['fid', 'title', 'data'] );
        $this->field( 'field_teaser', 'media', ['fid', 'title', 'data'] );
        $this->field( 'field_download', 'file', ['fid', 'display', 'description'] );
        $db->table( 'field_data_field_download' )->insert( $this->row( 'field_download', 1, ['fid' => 7, 'display' => 1, 'description' => ''] ) );
        $db->table( 'field_data_field_media' )->insert( [
            $this->row( 'field_media', 1, ['fid' => 1, 'title' => 'Photo'], 'und', 0 ),
            $this->row( 'field_media', 1, ['fid' => 2, 'title' => 'Manual'], 'und', 1 ),
            $this->row( 'field_media', 1, ['fid' => 3, 'title' => ''], 'und', 2 ),
            $this->row( 'field_media', 1, ['fid' => 4, 'title' => ''], 'und', 3 ),
            $this->row( 'field_media', 1, ['fid' => 5, 'title' => ''], 'und', 4 ),
        ] );
        $db->table( 'field_data_field_teaser' )->insert( $this->row( 'field_teaser', 1, ['fid' => 6] ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $content = array_values( array_filter( (array) json_decode( (string) json_encode( PageView::where( 'title', 'About' )->firstOrFail()->content ), true ),
            fn( $el ) => !in_array( $el['type'] ?? '', ['heading', 'html'], true ) || str_contains( (string) ( $el['data']['text'] ?? '' ), 'iframe' ) ) );
        $movie = File::where( 'path', 'like', '%/movie.mp4' )->value( 'id' );
        $content = array_values( array_filter( $content, function( $el ) use ( $movie, &$download ) {
            return ( $el['data']['file']['id'] ?? null ) === $movie ? !( $download = $el['type'] ) : true;
        } ) );
        $path = fn( int $idx ) => File::where( 'id', $content[$idx]['data']['file']['id'] ?? '' )->value( 'path' );

        // images become image elements, audios and videos players and other files file elements
        $this->assertSame( ['file', 'video', 'audio', 'html', 'image', 'image'], array_column( $content, 'type' ) );
        $this->assertStringEndsWith( '/doc.pdf', (string) $path( 0 ) );
        $this->assertSame( 'Manual', File::where( 'path', 'like', '%/doc.pdf' )->value( 'name' ) );
        $this->assertStringEndsWith( '/clip.mp4', (string) $path( 1 ) );
        $this->assertStringEndsWith( '/talk.mp3', (string) $path( 2 ) );
        $this->assertStringContainsString( 'youtube-nocookie.com/embed/dQw4w9WgXcQ', $content[3]['data']['text'] );
        $this->assertStringEndsWith( '/photo.jpg', (string) $path( 4 ) );
        $this->assertSame( 'Photo', File::where( 'path', 'like', '%/photo.jpg' )->value( 'name' ) );
        $this->assertStringEndsWith( '/other.jpg', (string) $path( 5 ) );

        // videos in file fields stay files
        $this->assertSame( 'file', $download ?? null );
    }


    public function testImportFilesFetchedAtOnce(): void
    {
        $this->field( 'field_image', 'image', ['fid', 'alt', 'title'] );
        $this->field( 'field_file_image_alt_text', 'text', ['value', 'format'], [], [], 'image', 'file' );
        $this->field( 'field_file_image_title_text', 'text', ['value', 'format'], [], [], 'image', 'file' );

        $this->tables( 'block_custom', 'block' );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'theme_default', 'value' => serialize( 'bartik' )] );
        $db->table( 'block' )->insert( ['module' => 'block', 'delta' => '1', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'pages' => ''] );
        $db->table( 'file_managed' )->insert( array_map( fn( $fid ) => ['fid' => $fid, 'filename' => "img{$fid}.jpg", 'uri' => "public://img{$fid}.jpg", 'filemime' => 'image/jpeg'], range( 1, 10 ) ) );
        $db->table( 'field_data_field_file_image_alt_text' )->insert( array_map( fn( $fid ) => $this->row( 'field_file_image_alt_text', $fid, ['value' => "Alt {$fid}"], 'und', 0, 'image', 'file' ), range( 1, 10 ) ) );
        $db->table( 'field_data_field_file_image_alt_text' )->insert( $this->row( 'field_file_image_alt_text', 8, ['value' => 'Second alt'], 'und', 1, 'image', 'file' ) );
        $db->table( 'field_data_field_image' )->insert( array_map( fn( $fid ) => $this->row( 'field_image', 1, ['fid' => $fid, 'alt' => '', 'title' => ''], 'und', $fid ), range( 1, 4 ) ) );
        $tags = fn( array $fids ) => implode( '', array_map( fn( $fid ) => '<p>[[{"fid":"' . $fid . '","type":"media"}]]</p>', $fids ) );
        $db->table( 'field_data_body' )->update( ['body_value' => $tags( [5, 6] )] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team'] );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 2, ['value' => $tags( [7, 8] ), 'summary' => '', 'format' => 'filtered_html'] ) );
        $db->table( 'block_custom' )->insert( ['bid' => 1, 'body' => $tags( [9, 10] ), 'info' => 'Gallery', 'format' => 'filtered_html'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );
        $queries = $this->queries( $db )->pluck( 'query' )->all();
        $db->disableQueryLog();

        // the records and texts of all images of a field or text are fetched by one query instead of one per image
        $this->assertCount( 3, array_filter( $queries, fn( $sql ) => str_contains( $sql, 'field_file_image_alt_text_value' ) ) );
        $this->assertCount( 3, array_filter( $queries, fn( $sql ) => str_contains( $sql, 'from "file_managed"' ) ) );
        $this->assertCount( 3, array_filter( $queries, fn( $sql ) => str_starts_with( $sql, 'select "fid", "uri", "filename", "filemime" from "file_managed"' ) ) );
        $this->assertSame( 10, File::where( 'name', 'like', 'Alt %' )->count() );
        $this->assertSame( 0, File::where( 'name', 'Second alt' )->count() );
    }


    public function testImportBeans(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'block_custom' );
        $schema->create( 'bean', function( Blueprint $table ) {
            $table->increments( 'bid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'delta' );
            $table->string( 'label' );
            $table->string( 'title' );
            $table->string( 'type' );
            $table->string( 'view_mode' )->default( 'default' );
            $table->unsignedInteger( 'changed' )->default( 1700000000 );
        } );
        $this->tables( 'block' );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'theme_default', 'value' => serialize( 'bartik' )] );
        $db->table( 'block_custom' )->insert( ['bid' => 1, 'body' => '<p>Call us</p>', 'info' => 'Contact', 'format' => 'filtered_html'] );
        $db->table( 'bean' )->insert( [
            ['bid' => 1, 'vid' => 1, 'delta' => 'promo-box', 'label' => 'Promo box', 'title' => 'Promo', 'type' => 'promo'],
            ['bid' => 2, 'vid' => 2, 'delta' => 'hours', 'label' => 'Hours', 'title' => '', 'type' => 'basic'],
            ['bid' => 3, 'vid' => 3, 'delta' => 'unplaced', 'label' => 'Unplaced', 'title' => '', 'type' => 'basic'],
        ] );
        $this->field( 'field_promo', 'text_long', ['value', 'format'], [], ['display' => ['default' => ['weight' => 1, 'label' => 'hidden']]], 'promo', 'bean' );
        $this->field( 'field_tagline', 'text', ['value', 'format'], [], ['display' => ['default' => ['weight' => 0, 'label' => 'hidden']]], 'promo', 'bean' );
        $this->field( 'field_hours', 'text_long', ['value', 'format'], [], ['display' => ['default' => ['label' => 'hidden']]], 'basic', 'bean' );
        $db->table( 'field_data_field_promo' )->insert( $this->row( 'field_promo', 1, ['value' => '<p>Save 20%</p>', 'format' => 'filtered_html'], 'und', 0, 'promo', 'bean' ) );
        $db->table( 'field_data_field_tagline' )->insert( $this->row( 'field_tagline', 1, ['value' => 'Best deals', 'format' => 'plain_text'], 'und', 0, 'promo', 'bean' ) );
        $db->table( 'field_data_field_hours' )->insert( [
            $this->row( 'field_hours', 2, ['value' => '<p>Mo-Fr 9-5</p>', 'format' => 'filtered_html'], 'und', 0, 'basic', 'bean' ),
            $this->row( 'field_hours', 3, ['value' => '<p>Never</p>', 'format' => 'filtered_html'], 'und', 0, 'basic', 'bean' ),
        ] );
        $db->table( 'block' )->insert( [
            ['module' => 'block', 'delta' => '1', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'weight' => 0, 'pages' => ''],
            ['module' => 'bean', 'delta' => 'promo-box', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'weight' => 1, 'pages' => ''],
            ['module' => 'bean', 'delta' => 'hours', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'weight' => 2, 'pages' => ''],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $contact = Element::where( 'name', 'Contact' )->sole();
        $promo = Element::where( 'name', 'like', 'Promo box %' )->orderBy( 'name' )->get();
        $hours = Element::where( 'name', 'Hours' )->sole();
        $refs = array_column( (array) json_decode( (string) json_encode( PageView::where( 'title', 'About' )->firstOrFail()->content ), true ), 'refid' );
        $texts = $promo->map( fn( $el ) => (string) json_encode( $el->data ) )->implode( '|' );

        // the beans are blocks with their fields in the order of the display, a bean type "basic" doesn't replace the custom block body
        $this->assertStringContainsString( 'Call us', (string) json_encode( $contact->data ) );
        $this->assertCount( 2, $promo );
        $this->assertLessThan( strpos( $texts, 'Save 20%' ), strpos( $texts, 'Best deals' ) );
        $this->assertStringContainsString( 'Mo-Fr 9-5', (string) json_encode( $hours->data ) );
        $this->assertSame( [(string) $contact->id, ...$promo->pluck( 'id' )->map( strval( ... ) )->all(), (string) $hours->id], $refs );
        $this->assertSame( 0, Element::where( 'name', 'Unplaced' )->count() );
    }


    public function testImportBeansWithoutCustomBlocks(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'bean', function( Blueprint $table ) {
            $table->increments( 'bid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'delta' );
            $table->string( 'label' );
            $table->string( 'title' );
            $table->string( 'type' );
            $table->string( 'view_mode' )->default( 'default' );
            $table->unsignedInteger( 'changed' )->default( 1700000000 );
        } );
        $this->tables( 'block' );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'theme_default', 'value' => serialize( 'bartik' )] );
        $db->table( 'bean' )->insert( ['bid' => 1, 'vid' => 1, 'delta' => 'hours', 'label' => 'Hours', 'title' => '', 'type' => 'basic'] );
        $this->field( 'field_hours', 'text_long', ['value', 'format'], [], ['display' => ['default' => ['label' => 'hidden']]], 'basic', 'bean' );
        $db->table( 'field_data_field_hours' )->insert( $this->row( 'field_hours', 1, ['value' => '<p>Mo-Fr 9-5</p>', 'format' => 'filtered_html'], 'und', 0, 'basic', 'bean' ) );
        $db->table( 'block' )->insert( ['module' => 'bean', 'delta' => 'hours', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'pages' => ''] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // beans are imported if the Drupal site has no custom blocks table
        $hours = Element::where( 'name', 'Hours' )->sole();
        $refs = array_column( (array) json_decode( (string) json_encode( PageView::where( 'title', 'About' )->firstOrFail()->content ), true ), 'refid' );

        $this->assertStringContainsString( 'Mo-Fr 9-5', (string) json_encode( $hours->data ) );
        $this->assertSame( [(string) $hours->id], $refs );
    }


    public function testImportBlocks(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'block_custom', 'block' );
        $schema->create( 'block_role', function( Blueprint $table ) {
            $table->string( 'module' );
            $table->string( 'delta' );
            $table->unsignedInteger( 'rid' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'theme_default', 'value' => serialize( 'bartik' )] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team'] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'team'] );
        $db->table( 'block_custom' )->insert( [
            ['bid' => 1, 'body' => '<p>Call us</p><p>[[{"fid":"9"}]]</p>', 'info' => 'Contact', 'format' => 'filtered_html'],
            ['bid' => 2, 'body' => '<p>Members only</p>', 'info' => 'Members', 'format' => 'filtered_html'],
            ['bid' => 3, 'body' => '<p>Never</p>', 'info' => 'Never', 'format' => 'filtered_html'],
            ['bid' => 4, 'body' => '<p>Guests</p>', 'info' => 'Guests', 'format' => 'filtered_html'],
        ] );
        $db->table( 'block' )->insert( [
            ['module' => 'block', 'delta' => '1', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'visibility' => 0, 'pages' => 'team'],
            ['module' => 'block', 'delta' => '1', 'theme' => 'seven', 'status' => 1, 'region' => 'footer', 'visibility' => 0, 'pages' => ''],
            ['module' => 'block', 'delta' => '2', 'theme' => 'bartik', 'status' => 1, 'region' => 'sidebar_first', 'visibility' => 0, 'pages' => ''],
            ['module' => 'block', 'delta' => '3', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'visibility' => 1, 'pages' => ''],
            ['module' => 'block', 'delta' => '4', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'visibility' => 0, 'pages' => ''],
            ['module' => 'system', 'delta' => 'main', 'theme' => 'bartik', 'status' => 1, 'region' => 'content', 'visibility' => 0, 'pages' => ''],
            ['module' => 'search', 'delta' => 'form', 'theme' => 'bartik', 'status' => 1, 'region' => 'sidebar_first', 'visibility' => 0, 'pages' => ''],
        ] );
        $db->table( 'block_role' )->insert( [
            ['module' => 'block', 'delta' => '2', 'rid' => 2],
            ['module' => 'block', 'delta' => '4', 'rid' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();

        $this->assertStringContainsString( 'search_form (search:form)', $output );
        $this->assertStringNotContainsString( 'system:main', $output );

        $element = Element::where( 'name', 'Contact' )->sole();
        $guests = Element::where( 'name', 'Guests' )->sole();
        $refs = fn( string $title ) => array_column( (array) json_decode( (string) json_encode( PageView::where( 'title', $title )->firstOrFail()->content ), true ), 'refid' );

        $this->assertStringContainsString( 'Call us', (string) json_encode( $element->data ) );
        $this->assertStringNotContainsString( '[[', (string) json_encode( $element->data ) );
        $this->assertSame( [(string) $element->id, (string) $guests->id], $refs( 'About' ) );
        $this->assertSame( [(string) $guests->id], $refs( 'Team' ) );
        $this->assertSame( 0, Element::whereIn( 'name', ['Members', 'Never'] )->count() );
    }


    public function testImportContexts(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'block_custom' );
        $schema->create( 'context', function( Blueprint $table ) {
            $table->string( 'name' )->primary();
            $table->string( 'description' )->default( '' );
            $table->string( 'tag' )->default( '' );
            $table->text( 'conditions' );
            $table->text( 'reactions' );
            $table->integer( 'condition_mode' )->default( 0 );
        } );
        $this->tables( 'role' );

        $context = fn( string $name, array $conditions, array $blocks, int $mode = 0 ) => [
            'name' => $name,
            'conditions' => serialize( array_map( fn( $values ) => ['values' => array_combine( $values, $values )], $conditions ) ),
            'reactions' => serialize( ['block' => ['blocks' => array_map( fn( $bid ) => ['module' => 'block', 'delta' => (string) $bid, 'region' => 'footer', 'weight' => -$bid], $blocks )]] ),
            'condition_mode' => $mode,
        ];

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( [
            ['name' => 'theme_default', 'value' => serialize( 'bartik' )],
            ['name' => 'context_status', 'value' => serialize( ['disabled' => true, 'enabled' => false] )],
        ] );
        $db->table( 'role' )->insert( [['rid' => 1, 'name' => 'anonymous user'], ['rid' => 2, 'name' => 'authenticated user']] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'article', 'title' => 'Team'] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'team'] );
        $db->table( 'block_custom' )->insert( array_map( fn( $bid, $name ) => ['bid' => $bid, 'body' => "<p>{$name}</p>", 'info' => $name, 'format' => 'filtered_html'],
            range( 1, 10 ), ['NotTeam', 'Pages', 'Everywhere', 'Disabled', 'Views', 'Mixed', 'Guests', 'Members', 'Enabled', 'German'] ) );
        $db->table( 'context' )->insert( [
            $context( 'not_team', ['path' => ['~team'], 'language' => ['en']], [1], 1 ),
            $context( 'pages', ['node' => ['page']], [2] ),
            $context( 'everywhere', ['sitewide' => ['1'], 'path' => ['team']], [3] ),
            $context( 'disabled', ['sitewide' => ['1']], [4] ),
            $context( 'views', ['views' => ['frontpage']], [5], 1 ),
            $context( 'mixed', ['path' => ['team', '~about']], [6] ),
            $context( 'guests', ['user' => ['anonymous user']], [7] ),
            $context( 'members', ['user' => ['authenticated user']], [8] ),
            $context( 'enabled', ['path' => ['about', 'team'], 'node' => ['page']], [9], 1 ),
            $context( 'any', ['path' => ['about'], 'node' => ['page']], [5] ),
            ['name' => 'empty', 'conditions' => serialize( [] ), 'reactions' => serialize( [] ), 'condition_mode' => 0],
            $context( 'never', [], [6] ),
            $context( 'german', ['language' => ['de']], [10] ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--types' => 'page,article'] ) );
        $output = Artisan::output();

        $refs = fn( string $title ) => Element::whereIn( 'id', array_column( (array) json_decode( (string) json_encode( PageView::where( 'title', $title )->firstOrFail()->content ), true ), 'refid' ) )
            ->orderBy( 'name' )->pluck( 'name' )->all();

        $this->assertSame( ['Enabled', 'Everywhere', 'Guests', 'NotTeam', 'Pages'], $refs( 'About' ) );
        $this->assertSame( ['Everywhere', 'Guests'], $refs( 'Team' ) );
        $this->assertSame( 0, Element::whereIn( 'name', ['Disabled', 'Views', 'Mixed', 'Members', 'German'] )->count() );
        $this->assertSame( ['footer'], array_values( array_unique( array_column( (array) json_decode( (string) json_encode( PageView::where( 'title', 'Team' )->firstOrFail()->content ), true ), 'group' ) ) ) );
        $this->assertStringContainsString( 'Skipped blocks of context "views"', $output );
        $this->assertStringContainsString( 'Skipped blocks of context "mixed"', $output );
        $this->assertStringContainsString( 'Skipped blocks of context "any"', $output );
        $this->assertStringContainsString( 'Skipped blocks of context "never"', $output );
        $this->assertStringNotContainsString( 'context "empty"', $output );
        $this->assertStringNotContainsString( 'context "disabled"', $output );
    }


    public function testImportDisplaySuite(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'ds_layout_settings', function( Blueprint $table ) {
            $table->string( 'id' )->primary();
            $table->string( 'entity_type' );
            $table->string( 'bundle' );
            $table->string( 'view_mode' );
            $table->string( 'layout' );
            $table->binary( 'settings' );
        } );
        $this->tables( 'field_group' );

        $display = fn( int $weight, string $label = 'above' ) => ['display' => [
            'default' => ['weight' => -$weight, 'label' => 'hidden'],
            'full' => ['weight' => $weight, 'label' => $label],
        ]];
        $this->field( 'field_count', 'number_integer', ['value'], [], $display( 1, 'inline' ) );
        $this->field( 'field_note', 'text', ['value', 'format'], [], $display( 5 ) );
        $this->field( 'field_side', 'text', ['value', 'format'], [], $display( 3 ) );
        $this->field( 'field_aside', 'text', ['value', 'format'], [], $display( 0 ) );
        $this->field( 'field_secret', 'text', ['value', 'format'], [], $display( 4 ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'field_data_field_count' )->insert( $this->row( 'field_count', 1, ['value' => 42] ) );
        $db->table( 'field_data_field_note' )->insert( $this->row( 'field_note', 1, ['value' => 'Grouped note'] ) );
        $db->table( 'field_data_field_side' )->insert( $this->row( 'field_side', 1, ['value' => 'Side text'] ) );
        $db->table( 'field_data_field_aside' )->insert( $this->row( 'field_aside', 1, ['value' => 'Aside text'] ) );
        $db->table( 'field_data_field_secret' )->insert( $this->row( 'field_secret', 1, ['value' => 'Secret text'] ) );
        $db->table( 'field_group' )->insert( [
            ['group_name' => 'group_box', 'entity_type' => 'node', 'bundle' => 'page', 'mode' => 'full', 'parent_name' => '',
                'data' => serialize( ['weight' => -1, 'children' => ['field_note']] )],
            ['group_name' => 'group_box', 'entity_type' => 'node', 'bundle' => 'page', 'mode' => 'default', 'parent_name' => '',
                'data' => serialize( ['weight' => 10, 'children' => ['field_secret']] )],
        ] );
        $db->table( 'ds_layout_settings' )->insert( [
            ['id' => 'node|page|full', 'entity_type' => 'node', 'bundle' => 'page', 'view_mode' => 'full', 'layout' => 'ds_2col_stacked',
                'settings' => serialize( ['regions' => [
                    'footer' => ['field_count'],
                    'right' => ['field_aside'],
                    'left' => ['title', 'field_side', 'group_box', 'field_note'],
                    'header' => ['body'],
                ]] )],
            ['id' => 'node|page|default', 'entity_type' => 'node', 'bundle' => 'page', 'view_mode' => 'default', 'layout' => 'ds_1col',
                'settings' => serialize( ['regions' => ['ds_content' => ['field_secret', 'field_count']]] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );

        // regions in reading order, fields in groups at the position of their group, others hidden
        $this->assertStringNotContainsString( 'Secret text', $content );
        $this->assertStringContainsString( 'Field_count:</strong> 42', $content );
        $this->assertLessThan( strpos( $content, 'Grouped note' ), strpos( $content, 'Welcome' ) );
        $this->assertLessThan( strpos( $content, 'Side text' ), strpos( $content, 'Grouped note' ) );
        $this->assertLessThan( strpos( $content, 'Aside text' ), strpos( $content, 'Side text' ) );
        $this->assertLessThan( strpos( $content, 'Field_count' ), strpos( $content, 'Aside text' ) );
        $this->assertSame( 1, substr_count( $content, 'Grouped note' ) );
    }


    public function testImportRedirectsAndMetatags(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'redirect', function( Blueprint $table ) {
            $table->increments( 'rid' );
            $table->string( 'source' );
            $table->string( 'redirect' );
            $table->string( 'language' )->default( 'und' );
            $table->integer( 'status_code' )->default( 0 );
            $table->integer( 'status' )->default( 1 );
        } );
        $schema->create( 'metatag', function( Blueprint $table ) {
            $table->string( 'entity_type' );
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'language' );
            $table->binary( 'data' );
        } );
        $schema->create( 'metatag_config', function( Blueprint $table ) {
            $table->increments( 'cid' );
            $table->string( 'instance' );
            $table->binary( 'config' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'redirect' )->insert( [
            ['source' => 'old-about', 'redirect' => 'node/1', 'status_code' => 0, 'status' => 1],
            ['source' => 'disabled', 'redirect' => 'node/1', 'status_code' => 0, 'status' => 0],
            ['source' => 'external', 'redirect' => 'https://example.org/', 'status_code' => 302, 'status' => 1],
        ] );
        $db->table( 'metatag' )->insert( [
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 1, 'language' => 'und', 'data' => serialize( ['description' => ['value' => 'Old']] )],
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 2, 'language' => 'und', 'data' => serialize( [
                'description' => ['value' => 'About the company'],
                'robots' => ['value' => ['noindex' => 'noindex', 'nofollow' => 0]],
            ] )],
            // meta tags of revisions newer than the current one aren't published
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 3, 'language' => 'und', 'data' => serialize( ['description' => ['value' => 'Unpublished']] )],
        ] );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['vid' => 2] );
        // meta tags with revision ID 0 are used for all revisions
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 4, 'type' => 'page', 'title' => 'Team'] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'team'] );
        $db->table( 'metatag' )->insert( ['entity_type' => 'node', 'entity_id' => 2, 'revision_id' => 0, 'language' => 'und', 'data' => serialize( ['description' => ['value' => 'Our team']] )] );
        $db->table( 'metatag_config' )->insert( ['instance' => 'global', 'config' => serialize( ['keywords' => ['value' => 'cms, drupal']] )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'title', 'About' )->firstOrFail();
        $meta = (string) json_encode( $page->meta );
        $redirects = $this->redirects();

        $this->assertStringContainsString( 'About the company', $meta );
        $this->assertStringNotContainsString( 'Old', $meta );
        $this->assertStringNotContainsString( 'Unpublished', $meta );
        $this->assertStringContainsString( 'cms, drupal', $meta );
        $this->assertStringContainsString( 'noindex', (string) json_encode( $page->config ) . $meta );
        $this->assertSame( '/about', $redirects['old-about'] ?? null );
        $this->assertSame( 'https://example.org/', $redirects['external'] ?? null );
        $this->assertArrayNotHasKey( 'disabled', $redirects );
        $this->assertStringContainsString( 'Our team', (string) json_encode( PageView::where( 'title', 'Team' )->firstOrFail()->meta ) );
    }


    public function testImportParagraphsAndFieldCollections(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'paragraphs_item', function( Blueprint $table ) {
            $table->unsignedInteger( 'item_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'bundle' );
            $table->string( 'field_name' );
            $table->integer( 'archived' )->default( 0 );
        } );
        $this->tables( 'field_collection_item' );

        $this->field( 'field_sections', 'paragraphs', ['value', 'revision_id'], [], ['display' => ['default' => ['weight' => 2]]] );
        $this->field( 'field_facts', 'field_collection', ['value', 'revision_id'], [], ['display' => ['default' => ['weight' => 1]]] );
        $this->field( 'field_text', 'text_long', ['value', 'format'], [], [], 'text', 'paragraphs_item' );
        $this->field( 'field_text', 'text_long', ['value', 'format'], [], [], 'field_facts', 'field_collection_item' );

        $db = DB::connection( 'drupal' );
        $db->table( 'paragraphs_item' )->insert( [
            ['item_id' => 1, 'revision_id' => 1, 'bundle' => 'text', 'field_name' => 'field_sections', 'archived' => 1],
            ['item_id' => 2, 'revision_id' => 2, 'bundle' => 'text', 'field_name' => 'field_sections', 'archived' => 0],
        ] );
        // same ID as the last paragraph
        $db->table( 'field_collection_item' )->insert( ['item_id' => 2, 'revision_id' => 2, 'field_name' => 'field_facts'] );
        $db->table( 'field_data_field_sections' )->insert( [
            $this->row( 'field_sections', 1, ['value' => 2, 'revision_id' => 2] ),
            $this->row( 'field_sections', 1, ['value' => 1, 'revision_id' => 1], 'und', 1 ),
        ] );
        $db->table( 'field_data_field_facts' )->insert( $this->row( 'field_facts', 1, ['value' => 2, 'revision_id' => 2] ) );
        $db->table( 'field_data_field_text' )->insert( [
            $this->row( 'field_text', 2, ['value' => '<p>Paragraph text</p>', 'format' => 'filtered_html'], 'und', 0, 'text', 'paragraphs_item' ),
            $this->row( 'field_text', 1, ['value' => '<p>Archived text</p>', 'format' => 'filtered_html'], 'und', 0, 'text', 'paragraphs_item' ),
            $this->row( 'field_text', 2, ['value' => '<p>Collection text</p>', 'format' => 'filtered_html'], 'und', 0, 'field_facts', 'field_collection_item' ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );

        $this->assertStringContainsString( 'Paragraph text', $content );
        $this->assertStringContainsString( 'Collection text', $content );
        $this->assertStringNotContainsString( 'Archived text', $content );
        $this->assertLessThan( strpos( $content, 'Paragraph text' ), strpos( $content, 'Collection text' ) );
    }


    public function testImportFieldCollections(): void
    {
        $this->tables( 'field_collection_item' );

        $this->field( 'field_facts', 'field_collection', ['value', 'revision_id'] );
        $this->field( 'field_text', 'text_long', ['value', 'format'], [], [], 'field_facts', 'field_collection_item' );

        $db = DB::connection( 'drupal' );
        $db->table( 'field_collection_item' )->insert( ['item_id' => 1, 'revision_id' => 1, 'field_name' => 'field_facts'] );
        $db->table( 'field_data_field_facts' )->insert( $this->row( 'field_facts', 1, ['value' => 1, 'revision_id' => 1] ) );
        $db->table( 'field_data_field_text' )->insert(
            $this->row( 'field_text', 1, ['value' => '<p>Collection text</p>', 'format' => 'filtered_html'], 'und', 0, 'field_facts', 'field_collection_item' )
        );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $this->assertStringContainsString( 'Collection text', $this->content( PageView::where( 'title', 'About' )->firstOrFail() ) );
    }


    public function testImportTermsAndBooks(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'taxonomy_vocabulary' );
        $schema->create( 'taxonomy_term_data', function( Blueprint $table ) {
            $table->increments( 'tid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'name' );
            $table->text( 'description' )->nullable();
            $table->string( 'format' )->nullable();
            $table->integer( 'weight' )->default( 0 );
        } );
        $this->tables( 'taxonomy_index' );
        $schema->create( 'menu_links', function( Blueprint $table ) {
            $table->increments( 'mlid' );
            $table->string( 'menu_name' );
            $table->unsignedInteger( 'plid' )->default( 0 );
            $table->string( 'link_path' );
            $table->string( 'link_title' );
            $table->integer( 'hidden' )->default( 0 );
            $table->integer( 'weight' )->default( 0 );
        } );
        $schema->create( 'book', function( Blueprint $table ) {
            $table->unsignedInteger( 'mlid' );
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'bid' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'book', 'title' => 'Manual'],
            ['nid' => 3, 'vid' => 3, 'type' => 'book', 'title' => 'Chapter'],
        ] );
        $db->table( 'url_alias' )->insert( [
            ['source' => 'taxonomy/term/7', 'alias' => 'tags/drupal'],
            ['source' => 'taxonomy/term/8', 'alias' => 'categories/other'],
            ['source' => 'node/2', 'alias' => 'manual'],
            ['source' => 'node/3', 'alias' => 'chapter'],
        ] );
        $db->table( 'taxonomy_vocabulary' )->insert( [['vid' => 1, 'machine_name' => 'tags'], ['vid' => 2, 'machine_name' => 'categories']] );
        $db->table( 'taxonomy_term_data' )->insert( [
            ['tid' => 7, 'vid' => 1, 'name' => 'Drupal', 'description' => '<p>All about Drupal</p>', 'format' => 'filtered_html'],
            ['tid' => 8, 'vid' => 2, 'name' => 'Other', 'description' => '', 'format' => 'filtered_html'],
        ] );
        $db->table( 'taxonomy_index' )->insert( ['nid' => 1, 'tid' => 7] );
        $db->table( 'menu_links' )->insert( [
            ['mlid' => 10, 'menu_name' => 'book-toc-2', 'plid' => 0, 'link_path' => 'node/2', 'link_title' => 'Manual'],
            ['mlid' => 11, 'menu_name' => 'book-toc-2', 'plid' => 10, 'link_path' => 'node/3', 'link_title' => 'Chapter'],
        ] );
        $db->table( 'book' )->insert( [
            ['mlid' => 10, 'nid' => 2, 'bid' => 2],
            ['mlid' => 11, 'nid' => 3, 'bid' => 2],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $selects = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_starts_with( $query, 'select' ) && str_contains( $query, ' from "taxonomy_index" ' ) );
        $db->disableQueryLog();

        // only the term and node IDs of the term index are used
        $this->assertContains( 'select "tid", "nid" from "taxonomy_index"', $selects->map( fn( $query ) => explode( ' where ', $query )[0] )->all() );

        $term = PageView::where( 'title', 'Drupal' )->firstOrFail();

        $this->assertStringContainsString( 'All about Drupal', $this->content( $term ) );
        $this->assertSame( 0, PageView::where( 'title', 'Other' )->count() );
        $this->assertSame( PageView::where( 'title', 'Manual' )->firstOrFail()->id, PageView::where( 'title', 'Chapter' )->firstOrFail()->parent_id );
    }


    public function testImportDrafts(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'node_revision' );
        $schema->create( 'field_revision_body', function( Blueprint $table ) {
            $table->string( 'entity_type' );
            $table->string( 'bundle' );
            $table->integer( 'deleted' )->default( 0 );
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'language' )->default( 'und' );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' )->nullable();
            $table->text( 'body_summary' )->nullable();
            $table->text( 'body_format' )->nullable();
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 4, 'type' => 'page', 'title' => 'Team'] );
        $db->table( 'node_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'title' => 'About'],
            ['nid' => 1, 'vid' => 2, 'title' => 'About moderated'],
            ['nid' => 1, 'vid' => 3, 'title' => 'About draft'],
            ['nid' => 2, 'vid' => 4, 'title' => 'Team'],
        ] );
        $db->table( 'field_revision_body' )->insert( [
            $this->row( 'body', 1, ['value' => '<p>Moderated text</p>', 'format' => 'filtered_html'] ) + ['body_summary' => ''],
            $this->row( 'body', 1, ['value' => '<p>Draft text</p>', 'format' => 'filtered_html'] ) + ['body_summary' => ''],
        ] );
        $db->table( 'field_revision_body' )->where( 'body_value', '<p>Moderated text</p>' )->update( ['revision_id' => 2] );
        $db->table( 'field_revision_body' )->where( 'body_value', '<p>Draft text</p>' )->update( ['revision_id' => 3] );

        $pending = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->versions()->where( 'published', false )->get()->all();

        // the newest revision is the draft
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $versions = $pending( 'About' );

        $this->assertCount( 1, $versions );
        $this->assertSame( 'About draft', $versions[0]->data->title );
        $this->assertStringContainsString( 'Draft text', (string) json_encode( $versions[0]->aux ) );
        $this->assertStringContainsString( 'Hello', $this->content( PageView::where( 'name', 'About' )->firstOrFail() ) );
        $this->assertSame( [], $pending( 'Team' ) );

        // the current revision of the Workbench Moderation module is the draft
        $schema->create( 'workbench_moderation_node_history', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->integer( 'current' )->default( 0 );
        } );
        $db->table( 'workbench_moderation_node_history' )->insert( [
            ['nid' => 1, 'vid' => 2, 'current' => 1],
            ['nid' => 1, 'vid' => 3, 'current' => 0],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $versions = $pending( 'About' );

        $this->assertCount( 1, $versions );
        $this->assertSame( 'About moderated', $versions[0]->data->title );
        $this->assertStringContainsString( 'Moderated text', (string) json_encode( $versions[0]->aux ) );
    }


    public function testImportDraftsStateFlow(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'node_revision' );
        $schema->create( 'state_flow_states', function( Blueprint $table ) {
            $table->string( 'entity_type' );
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->integer( 'status' )->default( 0 );
        } );
        $this->field( 'title_field', 'text', ['value', 'format'] );
        $schema->create( 'field_revision_title_field', function( Blueprint $table ) {
            $table->string( 'entity_type' );
            $table->string( 'bundle' );
            $table->integer( 'deleted' )->default( 0 );
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'language' )->default( 'und' );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'title_field_value' )->nullable();
            $table->text( 'title_field_format' )->nullable();
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'title' => 'About'],
            ['nid' => 1, 'vid' => 2, 'title' => 'About state'],
            ['nid' => 1, 'vid' => 3, 'title' => 'About newest'],
        ] );
        $db->table( 'state_flow_states' )->insert( [
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 2, 'status' => 1],
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 3, 'status' => 0],
        ] );
        $db->table( 'field_revision_title_field' )->insert( ['entity_type' => 'node', 'bundle' => 'page', 'entity_id' => 1, 'revision_id' => 2, 'title_field_value' => 'About titled'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $version = PageView::where( 'name', 'About' )->firstOrFail()->versions()->where( 'published', false )->sole();

        $this->assertSame( 'About titled', $version->data->title );
    }


    public function testImportMenuTranslations(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'menu_links', function( Blueprint $table ) {
            $table->increments( 'mlid' );
            $table->string( 'menu_name' );
            $table->unsignedInteger( 'plid' )->default( 0 );
            $table->string( 'link_path' );
            $table->string( 'link_title' );
            $table->integer( 'hidden' )->default( 0 );
            $table->integer( 'weight' )->default( 0 );
            $table->string( 'language' )->default( 'und' );
            $table->unsignedInteger( 'i18n_tsid' )->default( 0 );
        } );
        $this->locales();

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'ueber-uns', 'language' => 'de'] );
        $db->table( 'menu_links' )->insert( [
            ['mlid' => 1, 'menu_name' => 'main-menu', 'link_path' => 'node/1', 'link_title' => 'About us', 'language' => 'en', 'i18n_tsid' => 5],
            ['mlid' => 2, 'menu_name' => 'main-menu', 'link_path' => 'node/2', 'link_title' => 'Wir', 'language' => 'de', 'i18n_tsid' => 5],
            ['mlid' => 3, 'menu_name' => 'main-menu', 'link_path' => 'https://shop.example.com', 'link_title' => 'Shop', 'language' => 'und', 'i18n_tsid' => 0],
            ['mlid' => 4, 'menu_name' => 'main-menu', 'link_path' => 'https://example.fr', 'link_title' => 'France', 'language' => 'fr', 'i18n_tsid' => 0],
        ] );
        $this->locale( 'menu', 'item:3:title', 'Shop', ['de' => 'Laden'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped 1 menu links of other languages than "en" which aren\'t translations', Artisan::output() );

        $about = PageView::where( 'title', 'About' )->firstOrFail();
        $shop = PageView::where( 'name', 'Shop' )->firstOrFail();

        $this->assertSame( 'About us', $about->name );
        $this->assertSame( 'Wir', PageView::language( 'de' )->findOrFail( $about->id )->name );
        $this->assertSame( 'Laden', PageView::language( 'de' )->findOrFail( $shop->id )->name );
        $this->assertSame( 0, PageView::where( 'name', 'France' )->count() );
    }


    public function testImportTermTranslations(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'taxonomy_vocabulary' );
        $schema->create( 'taxonomy_term_data', function( Blueprint $table ) {
            $table->increments( 'tid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'name' );
            $table->text( 'description' )->nullable();
            $table->string( 'format' )->nullable();
            $table->integer( 'weight' )->default( 0 );
            $table->string( 'language' )->default( 'und' );
            $table->unsignedInteger( 'i18n_tsid' )->default( 0 );
        } );
        $this->tables( 'taxonomy_index' );
        $this->locales();
        $this->field( 'field_tags', 'taxonomy_term_reference', ['tid'] );
        $this->field( 'field_topic', 'entityreference', ['target_id'], ['target_type' => 'taxonomy_term'] );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'field_data_body' )->update( ['body_value' => '<p>Read <a href="/taxonomy/term/8">news</a> and <a href="/taxonomy/term/9#top">events</a></p>'] );
        $db->table( 'field_data_field_topic' )->insert( $this->row( 'field_topic', 1, ['target_id' => 8] ) );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1] );
        $db->table( 'url_alias' )->insert( [
            ['source' => 'node/2', 'alias' => 'ueber-uns', 'language' => 'de'],
            ['source' => 'taxonomy/term/7', 'alias' => 'tags/news', 'language' => 'en'],
            ['source' => 'taxonomy/term/8', 'alias' => 'schlagworte/neuigkeiten', 'language' => 'de'],
            ['source' => 'taxonomy/term/9', 'alias' => 'tags/events', 'language' => 'und'],
        ] );
        $db->table( 'taxonomy_vocabulary' )->insert( ['vid' => 1, 'machine_name' => 'tags'] );
        $db->table( 'taxonomy_term_data' )->insert( [
            ['tid' => 7, 'vid' => 1, 'name' => 'News', 'description' => '<p>All news</p>', 'format' => 'filtered_html', 'language' => 'en', 'i18n_tsid' => 3],
            ['tid' => 8, 'vid' => 1, 'name' => 'Neuigkeiten', 'description' => '<p>Alle Neuigkeiten</p>', 'format' => 'filtered_html', 'language' => 'de', 'i18n_tsid' => 3],
            ['tid' => 9, 'vid' => 1, 'name' => 'Events', 'description' => '', 'format' => 'filtered_html', 'language' => 'und', 'i18n_tsid' => 0],
        ] );
        // nodes can be tagged with the translated term
        $db->table( 'taxonomy_index' )->insert( [['nid' => 1, 'tid' => 8], ['nid' => 1, 'tid' => 9]] );
        $db->table( 'field_data_field_tags' )->insert( $this->row( 'field_tags', 1, ['tid' => 8] ) );
        $this->locale( 'taxonomy', 'term:9:name', 'Events', ['de' => 'Veranstaltungen'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $terms = $this->queries( $db )->filter( fn( $query ) => str_starts_with( $query['query'], 'select * from "taxonomy_term_data" where "tid" in' ) );
        $db->disableQueryLog();
        $output = Artisan::output();

        // translated terms aren't fetched as terms of their own
        $this->assertContains( [7, 9], $terms->pluck( 'bindings' )->all() );
        $this->assertNotContains( [7, 8, 9], $terms->pluck( 'bindings' )->all() );
        $this->assertStringContainsString( 'Term pages: 2 imported, 0 updated, 0 unchanged (2/2 term pages processed).', $output );
        // without language prefix, translations with the URL of their source term are skipped
        $this->assertStringContainsString( 'Skipped translation: Veranstaltungen (/tags/events) [de] (URL is used by another page)', $output );

        $news = PageView::where( 'name', 'News' )->firstOrFail();
        $events = PageView::where( 'name', 'Events' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $news->id );

        $this->assertSame( 0, PageView::where( 'name', 'Neuigkeiten' )->where( 'lang', 'en' )->count() );
        $this->assertSame( ['Neuigkeiten', 'schlagworte/neuigkeiten'], [$de->name, $de->path] );
        $this->assertStringContainsString( 'Alle Neuigkeiten', (string) json_encode( $de->content ) );
        $this->assertStringContainsString( 'href=\\"/about\\"', $this->content( $news ) );
        $this->assertNull( PageView::language( 'de' )->find( $events->id ) );

        // term links in texts and term references point to the pages of the source terms
        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );
        $this->assertSame( 3, substr_count( $content, 'href=\\"/tags/news\\"' ) );
        $this->assertStringContainsString( 'href=\\"/tags/events#top\\"', $content );
    }


    public function testImportBlockTranslations(): void
    {
        $this->tables( 'block_custom', 'block' );
        $this->locales();

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'theme_default', 'value' => serialize( 'bartik' )] );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1] );
        $db->table( 'url_alias' )->insert( ['source' => 'node/2', 'alias' => 'ueber-uns', 'language' => 'de'] );
        $db->table( 'block_custom' )->insert( ['bid' => 1, 'body' => '<p>Call us</p>', 'info' => 'Contact', 'format' => 'filtered_html'] );
        $db->table( 'block' )->insert( ['module' => 'block', 'delta' => '1', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'pages' => ''] );
        $this->locale( 'blocks', 'block:1:body', '<p>Call us</p>', ['de' => '<p>Rufen Sie an</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $data = Element::where( 'name', 'Contact' )->get()->map( fn( $element ) => (string) json_encode( $element->data ) )->implode( ' ' );

        $this->assertStringContainsString( 'Call us', $data );
        $this->assertStringContainsString( 'Rufen Sie an', $data );
    }


    public function testFileCachesAreLimited(): void
    {
        $this->field( 'field_file_image_alt_text', 'text', ['value', 'format'], [], [], 'image', 'file' );

        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( array_map( fn( $fid ) => ['fid' => $fid, 'filename' => "img{$fid}.jpg", 'uri' => "public://img{$fid}.jpg", 'filemime' => 'image/jpeg'], range( 1, 6 ) ) );
        $db->table( 'field_data_field_file_image_alt_text' )->insert( array_map( fn( $fid ) => $this->row( 'field_file_image_alt_text', $fid, ['value' => "Alt {$fid}"], 'und', 0, 'image', 'file' ), range( 1, 6 ) ) );

        $reader = new class( $db, fn() => null ) extends Drupal7 {
            protected const CACHE_SIZE = 4;

            /**
             * @param array<int, int> $fids
             */
            public function load( array $fids ): void
            {
                $this->prefetchFiles( $fids );
            }

            public function record( int $fid ): ?\stdClass
            {
                return $this->file( $fid );
            }

            public function alt( int $fid ): string
            {
                return $this->fileText( $fid, 'alt' );
            }
        };
        $queries = fn( string $sql ) => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], $sql ) )->count();

        $db->enableQueryLog();

        // the oldest file records and texts are removed if there are too many, the last added ones are kept
        $reader->load( [1, 2, 3] );
        $reader->load( [4, 5] );
        $this->assertSame( 'img5.jpg', $reader->record( 5 )->filename ?? null );
        $this->assertSame( 'Alt 4', $reader->alt( 4 ) );
        $this->assertSame( 2, $queries( 'from "file_managed"' ) );
        $this->assertSame( 2, $queries( 'from "field_data_field_file_image_alt_text"' ) );
        $this->assertSame( 'img1.jpg', $reader->record( 1 )->filename ?? null );
        $this->assertSame( 'Alt 1', $reader->alt( 1 ) );
        $this->assertSame( 3, $queries( 'from "file_managed"' ) );
        $this->assertSame( 3, $queries( 'from "field_data_field_file_image_alt_text"' ) );

        // single records are limited too
        foreach( [6, 2, 3] as $fid )
        {
            $this->assertSame( "img{$fid}.jpg", $reader->record( $fid )->filename ?? null );
            $this->assertSame( "Alt {$fid}", $reader->alt( $fid ) );
        }

        $this->assertSame( 'img4.jpg', $reader->record( 4 )->filename ?? null );
        $this->assertSame( 'Alt 4', $reader->alt( 4 ) );
        $this->assertSame( 7, $queries( 'from "file_managed"' ) );
        $this->assertSame( 7, $queries( 'from "field_data_field_file_image_alt_text"' ) );

        $db->disableQueryLog();
    }


    public function testMapsTranslationNodes(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Duplicate', 'language' => 'de', 'tnid' => 1],
            ['nid' => 4, 'vid' => 4, 'type' => 'page', 'title' => 'Orphan', 'language' => 'fr', 'tnid' => 9],
            ['nid' => 5, 'vid' => 5, 'type' => 'page', 'title' => 'Plain', 'language' => 'und', 'tnid' => 0],
            ['nid' => 10, 'vid' => 10, 'type' => 'page', 'title' => 'Kette', 'language' => 'de', 'tnid' => 11],
            ['nid' => 11, 'vid' => 11, 'type' => 'page', 'title' => 'Chain', 'language' => 'en', 'tnid' => 12],
            ['nid' => 13, 'vid' => 13, 'type' => 'page', 'title' => 'Sub', 'language' => 'fr', 'tnid' => 2],
            ['nid' => 14, 'vid' => 14, 'type' => 'page', 'title' => 'Same', 'language' => 'en', 'tnid' => 1],
        ] );

        $warnings = [];
        $reader = new class( $db, function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; } ) extends Drupal7 {
            /** @return array{0: array<int, int>, 1: array<int, array<int, int>>, 2: array<int, array{nid: int, langcode: string, set: bool}>} */
            public function mapped(): array
            {
                return [$this->map(), $this->sources, array_map( fn( $nid ) => $this->mapping( $nid ), [1 => 1, 2 => 2, 4 => 4, 5 => 5, 10 => 10, 11 => 11, 13 => 13] )];
            }
        };
        [$map, $sources, $mappings] = $reader->mapped();
        ksort( $map );

        // translation nodes are mapped to their source node, the ones without existing source node are source nodes
        $this->assertSame( [1 => 1, 2 => 1, 4 => 4, 5 => 5, 10 => 11, 11 => 11, 13 => 2], $map );
        $this->assertSame( [1 => [1, 2], 11 => [11, 10]], $sources );
        // translation nodes in the language of their source node or of another translation are skipped
        $this->assertSame( [
            'Skipped Drupal translation node 3, node 1 already has a translation in "de"',
            'Skipped Drupal translation node 14, node 1 already has a translation in "en"',
        ], $warnings );
        $this->assertSame( [
            1 => ['nid' => 1, 'langcode' => 'en', 'set' => true],
            2 => ['nid' => 1, 'langcode' => 'de', 'set' => true],
            4 => ['nid' => 4, 'langcode' => 'fr', 'set' => true],
            5 => ['nid' => 5, 'langcode' => 'en', 'set' => false],
            10 => ['nid' => 11, 'langcode' => 'de', 'set' => true],
            11 => ['nid' => 11, 'langcode' => 'en', 'set' => true],
            13 => ['nid' => 2, 'langcode' => 'fr', 'set' => true],
        ], $mappings );
    }


    public function testTranslationsFetchTranslatedNodesOnly(): void
    {
        $this->tables( 'entity_translation' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['language' => 'en', 'tnid' => 1] );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Über uns', 'language' => 'de', 'tnid' => 1],
            ['nid' => 5, 'vid' => 5, 'type' => 'page', 'title' => 'Plain', 'language' => 'en', 'tnid' => 0],
            ['nid' => 6, 'vid' => 6, 'type' => 'page', 'title' => 'Entity', 'language' => 'en', 'tnid' => 0],
            ['nid' => 7, 'vid' => 7, 'type' => 'page', 'title' => 'Neutral', 'language' => 'en', 'tnid' => 0],
        ] );
        $db->table( 'entity_translation' )->insert( [
            ['entity_type' => 'node', 'entity_id' => 6, 'language' => 'fr'],
            ['entity_type' => 'node', 'entity_id' => 7, 'language' => 'und'],
            ['entity_type' => 'taxonomy_term', 'entity_id' => 5, 'language' => 'fr'],
        ] );

        $reader = new Drupal7( $db, fn() => null );

        $db->enableQueryLog();
        $translations = $reader->translations( [1, 5, 6, 7], true );
        $queries = $this->queries( $db )->filter( fn( $query ) => str_starts_with( $query['query'], 'select "nid", "vid"' ) && str_contains( $query['query'], '"nid" in' ) );
        $db->disableQueryLog();

        // only the nodes with translation nodes or entity translations are fetched
        $this->assertSame( [[1, 2, 6]], $queries->map( fn( $query ) => array_map( 'intval', $query['bindings'] ) )->values()->all() );
        $this->assertSame( [['de', 1], ['fr', 6]], $translations->map( fn( $node ) => [$node->langcode, (int) $node->nid] )->all() );
    }


    public function testChangedUsesNewerRevisionsOnly(): void
    {
        $this->tables( 'node_revision' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->where( 'nid', 1 )->update( ['vid' => 2, 'changed' => 1000] );
        $db->table( 'node' )->insert( ['nid' => 2, 'vid' => 3, 'type' => 'page', 'title' => 'Team', 'changed' => 1000] );
        $db->table( 'node_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'title' => 'About old', 'timestamp' => 5000],
            ['nid' => 1, 'vid' => 2, 'title' => 'About', 'timestamp' => 4000],
            ['nid' => 1, 'vid' => 4, 'title' => 'About draft', 'timestamp' => 2000],
            ['nid' => 1, 'vid' => 5, 'title' => 'About newer draft', 'timestamp' => 3000],
            ['nid' => 2, 'vid' => 3, 'title' => 'Team', 'timestamp' => 6000],
        ] );

        // the timestamps of the current and older revisions aren't used, only the ones of the drafts
        $this->assertSame( [1 => 3000, 2 => 1000], ( new Drupal7( $db, fn() => null ) )->changed( [1, 2] ) );
    }


    public function testTitleCachesAreLimited(): void
    {
        $this->field( 'title_field', 'text', ['value', 'format'] );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( array_map( fn( $nid ) => ['nid' => $nid, 'vid' => $nid, 'type' => 'page', 'title' => "Node {$nid}"], range( 2, 5 ) ) );
        $db->table( 'field_data_title_field' )->insert( array_map( fn( $nid ) => $this->row( 'title_field', $nid, ['value' => "Title {$nid}"] ), range( 1, 5 ) ) );

        $reader = new class( $db, fn() => null ) extends Drupal7 {
            protected const CACHE_SIZE = 4;

            /**
             * @param array<int, int> $nids
             */
            public function load( array $nids ): void
            {
                $this->preload( $nids );
            }

            /** @return array{0: array<int, int>, 1: array<int, int>} */
            public function cached(): array
            {
                return [array_keys( $this->schedules ), array_keys( $this->titles )];
            }
        };
        $queries = fn() => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "field_data_title_field"' ) )->count();

        $db->enableQueryLog();

        // the titles and schedules of the oldest nodes are removed if there are too many, the last added ones are kept
        $reader->load( [1, 2, 3] );
        $reader->load( [4, 5] );
        $this->assertSame( [[4, 5], [4, 5]], $reader->cached() );
        $reader->load( [5] );
        $this->assertSame( 2, $queries() );
        $reader->load( [1] );
        $this->assertSame( [[4, 5, 1], [4, 5, 1]], $reader->cached() );
        $this->assertSame( 3, $queries() );

        $db->disableQueryLog();
    }


    public function testTermIndexLimitsRowsPerTermAndTranslations(): void
    {
        $this->tables( 'taxonomy_index' );
        Schema::connection( 'drupal' )->create( 'taxonomy_term_data', function( Blueprint $table ) {
            $table->increments( 'tid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'name' );
            $table->string( 'language' )->default( 'und' );
            $table->unsignedInteger( 'i18n_tsid' )->default( 0 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'taxonomy_term_data' )->insert( [
            ['tid' => 1, 'vid' => 1, 'name' => 'News', 'language' => 'en', 'i18n_tsid' => 1],
            ['tid' => 2, 'vid' => 1, 'name' => 'Neuigkeiten', 'language' => 'de', 'i18n_tsid' => 1],
            ['tid' => 3, 'vid' => 1, 'name' => 'Events', 'language' => 'und', 'i18n_tsid' => 0],
        ] );
        $db->table( 'taxonomy_index' )->insert( [
            ['nid' => 10, 'tid' => 1, 'sticky' => 0, 'created' => 100],
            ['nid' => 11, 'tid' => 1, 'sticky' => 0, 'created' => 300],
            ['nid' => 12, 'tid' => 2, 'sticky' => 0, 'created' => 200],
            ['nid' => 13, 'tid' => 2, 'sticky' => 0, 'created' => 50],
            ['nid' => 14, 'tid' => 3, 'sticky' => 0, 'created' => 100],
            ['nid' => 15, 'tid' => 3, 'sticky' => 0, 'created' => 300],
            ['nid' => 16, 'tid' => 3, 'sticky' => 0, 'created' => 200],
        ] );
        $reader = new Drupal7( $db, fn() => null );
        $rows = fn( ?int $limit ) => $reader->termIndex( [1, 3], $limit )->map( fn( $row ) => [(int) $row->tid, (int) $row->nid] )->values()->all();

        // the newest rows of a term and its translations are limited together and mapped to the source term
        $this->assertSame( [[1, 11], [1, 12], [3, 15], [3, 16]], $rows( 2 ) );
        // all rows are fetched without limit
        $this->assertSame( [[1, 11], [3, 15], [1, 12], [3, 16], [1, 10], [3, 14], [1, 13]], $rows( null ) );
    }


    public function testConfigsAreBuiltOnce(): void
    {
        $db = DB::connection( 'drupal' );
        $reader = new Drupal7( $db, fn() => null );
        $configs = $reader->configs();

        $db->enableQueryLog();
        $this->assertSame( $configs, $reader->configs() );
        $again = $db->getQueryLog();
        $db->disableQueryLog();

        // the configuration is only built once
        $this->assertSame( [], $again );
    }


    public function testImportFileEntityTexts(): void
    {
        $this->field( 'field_image', 'image', ['fid', 'alt', 'title'] );
        $this->field( 'field_file_image_alt_text', 'text', ['value', 'format'], [], [], 'image', 'file' );
        $this->field( 'field_file_image_title_text', 'text', ['value', 'format'], [], [], 'image', 'file' );

        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'filename' => 'logo.png', 'uri' => 'public://logo.png', 'filemime' => 'image/png'],
            ['fid' => 2, 'filename' => 'photo.jpg', 'uri' => 'public://photo.jpg', 'filemime' => 'image/jpeg'],
            ['fid' => 3, 'filename' => 'banner.jpg', 'uri' => 'public://banner.jpg', 'filemime' => 'image/jpeg'],
        ] );
        $db->table( 'field_data_field_file_image_alt_text' )->insert( [
            $this->row( 'field_file_image_alt_text', 1, ['value' => 'Company logo'], 'und', 0, 'image', 'file' ),
            $this->row( 'field_file_image_alt_text', 2, ['value' => 'Team photo'], 'und', 0, 'image', 'file' ),
            $this->row( 'field_file_image_alt_text', 3, ['value' => 'Banner'], 'und', 0, 'image', 'file' ),
        ] );
        $this->field( 'field_media', 'media', ['fid', 'title', 'data'] );
        $db->table( 'field_data_field_media' )->insert( $this->row( 'field_media', 1, ['fid' => 3, 'title' => ''] ) );
        $db->table( 'field_data_field_file_image_title_text' )->insert( $this->row( 'field_file_image_title_text', 1, ['value' => 'Our logo'], 'und', 0, 'image', 'file' ) );
        $db->table( 'field_data_field_image' )->insert( $this->row( 'field_image', 1, ['fid' => 2, 'alt' => '', 'title' => ''] ) );
        $db->table( 'field_data_body' )->update( ['body_value' => '<p>Intro</p><p>[[{"fid":"1","type":"media"}]]</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        // the alternative texts of the embedded images are the file names
        $this->assertStringContainsString( '"type":"image"', $this->content( PageView::where( 'title', 'About' )->firstOrFail() ) );
        $this->assertSame( 1, File::where( 'name', 'Company logo' )->where( 'path', 'like', '%/logo.png' )->count() );
        $this->assertSame( 1, File::where( 'name', 'Team photo' )->count() );
        $this->assertSame( 1, File::where( 'name', 'Banner' )->where( 'path', 'like', '%/banner.jpg' )->count() );
    }


    public function testImportSinceImportsChangedNodesAndAllBlocks(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'menu_links', function( Blueprint $table ) {
            $table->increments( 'mlid' );
            $table->string( 'menu_name' );
            $table->unsignedInteger( 'plid' )->default( 0 );
            $table->string( 'link_path' );
            $table->string( 'link_title' );
            $table->integer( 'hidden' )->default( 0 );
            $table->integer( 'weight' )->default( 0 );
        } );
        $schema->create( 'taxonomy_term_data', function( Blueprint $table ) {
            $table->increments( 'tid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'name' );
            $table->text( 'description' )->nullable();
            $table->string( 'format' )->nullable();
            $table->integer( 'weight' )->default( 0 );
        } );
        $this->tables( 'taxonomy_index', 'block_custom', 'block' );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'theme_default', 'value' => serialize( 'bartik' )] );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team'],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Jobs'],
        ] );
        $db->table( 'url_alias' )->insert( [['source' => 'node/2', 'alias' => 'team'], ['source' => 'node/3', 'alias' => 'jobs']] );
        $db->table( 'field_data_body' )->insert( [
            $this->row( 'body', 2, ['value' => '<p>Team text</p>', 'summary' => '', 'format' => 'filtered_html'] ),
            $this->row( 'body', 3, ['value' => '<p>Jobs text</p>', 'summary' => '', 'format' => 'filtered_html'] ),
        ] );
        $db->table( 'menu_links' )->insert( ['mlid' => 1, 'menu_name' => 'main-menu', 'link_path' => 'node/1', 'link_title' => 'About us'] );
        $db->table( 'taxonomy_term_data' )->insert( ['tid' => 7, 'vid' => 1, 'name' => 'News'] );
        $db->table( 'taxonomy_index' )->insert( ['nid' => 2, 'tid' => 7] );
        $db->table( 'block_custom' )->insert( ['bid' => 1, 'body' => '<p>Call us</p>', 'info' => 'Contact', 'format' => 'filtered_html'] );
        $db->table( 'block' )->insert( ['module' => 'block', 'delta' => '1', 'theme' => 'bartik', 'status' => 1, 'region' => 'footer', 'pages' => ''] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // Drupal 7 doesn't store when blocks were changed, so they are imported again, nodes only if their changed date is newer
        $db->table( 'field_data_body' )->update( ['body_value' => '<p>Changed</p>'] );
        $db->table( 'node' )->where( 'nid', 3 )->update( ['changed' => strtotime( '2026-03-01' )] );
        $db->table( 'block_custom' )->update( ['body' => '<p>Write us</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-01-01'] ) );

        $this->assertStringContainsString( 'Unchanged: Team', Artisan::output() );
        $this->assertStringContainsString( 'Team text', $this->content( PageView::where( 'name', 'Team' )->firstOrFail() ) );
        $this->assertStringContainsString( 'Changed', $this->content( PageView::where( 'name', 'Jobs' )->firstOrFail() ) );
        $this->assertStringContainsString( 'Write us', (string) json_encode( Element::where( 'name', 'Contact' )->sole()->data ) );
    }


    public function testImportPanelizer(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'panelizer_entity', function( Blueprint $table ) {
            $table->string( 'entity_type' );
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'view_mode' );
            $table->unsignedInteger( 'did' );
        } );
        $schema->create( 'panelizer_defaults', function( Blueprint $table ) {
            $table->increments( 'pnid' );
            $table->string( 'name' );
            $table->string( 'entity_type' );
            $table->string( 'panelizer_type' );
            $table->string( 'view_mode' );
            $table->unsignedInteger( 'did' );
        } );
        $schema->create( 'panels_pane', function( Blueprint $table ) {
            $table->increments( 'pid' );
            $table->unsignedInteger( 'did' );
            $table->string( 'panel' );
            $table->string( 'type' );
            $table->string( 'subtype' );
            $table->integer( 'shown' )->default( 1 );
            $table->integer( 'position' )->default( 0 );
            $table->binary( 'configuration' );
        } );

        $this->field( 'field_intro', 'text', ['value', 'format'], [], ['display' => ['default' => ['weight' => 5]]] );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team'],
            ['nid' => 3, 'vid' => 3, 'type' => 'article', 'title' => 'Plain'],
            ['nid' => 4, 'vid' => 4, 'type' => 'page', 'title' => 'Jobs'],
            ['nid' => 5, 'vid' => 5, 'type' => 'page', 'title' => 'Empty'],
        ] );
        $db->table( 'url_alias' )->insert( [['source' => 'node/2', 'alias' => 'team'], ['source' => 'node/3', 'alias' => 'plain'], ['source' => 'node/4', 'alias' => 'jobs'], ['source' => 'node/5', 'alias' => 'empty']] );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 2, ['value' => '<p>Team text</p>', 'summary' => '', 'format' => 'filtered_html'] ) );
        $db->table( 'field_data_body' )->insert( $this->row( 'body', 3, ['value' => '<p>Plain text</p>', 'summary' => '', 'format' => 'filtered_html'], 'und', 0, 'article' ) );
        $db->table( 'field_data_field_intro' )->insert( [
            $this->row( 'field_intro', 1, ['value' => 'About intro'] ),
            $this->row( 'field_intro', 2, ['value' => 'Team intro'] ),
        ] );
        $db->table( 'panelizer_entity' )->insert( [
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 1, 'view_mode' => 'teaser', 'did' => 3],
            ['entity_type' => 'node', 'entity_id' => 1, 'revision_id' => 1, 'view_mode' => 'page_manager', 'did' => 1],
            ['entity_type' => 'node', 'entity_id' => 2, 'revision_id' => 2, 'view_mode' => 'page_manager', 'did' => 0],
            ['entity_type' => 'node', 'entity_id' => 4, 'revision_id' => 4, 'view_mode' => 'page_manager', 'did' => 4],
            ['entity_type' => 'node', 'entity_id' => 5, 'revision_id' => 5, 'view_mode' => 'page_manager', 'did' => 5],
        ] );
        $db->table( 'panelizer_defaults' )->insert( ['name' => 'node:page:default', 'entity_type' => 'node', 'panelizer_type' => 'page', 'view_mode' => 'page_manager', 'did' => 2] );
        $db->table( 'panels_pane' )->insert( [
            ['did' => 1, 'panel' => 'left', 'type' => 'custom', 'subtype' => 'custom', 'position' => 0, 'configuration' => serialize( ['title' => 'Sidebar', 'body' => '<p>Pane text</p>', 'format' => 'filtered_html'] )],
            ['did' => 1, 'panel' => 'middle', 'type' => 'entity_field', 'subtype' => 'node:body', 'position' => 1, 'configuration' => serialize( [] )],
            ['did' => 1, 'panel' => 'middle', 'type' => 'views', 'subtype' => 'news', 'position' => 2, 'configuration' => serialize( [] )],
            ['did' => 2, 'panel' => 'middle', 'type' => 'views', 'subtype' => 'news', 'position' => 3, 'configuration' => serialize( [] )],
            ['did' => 1, 'panel' => 'middle', 'type' => 'custom', 'subtype' => 'custom', 'position' => 3, 'configuration' => serialize( ['body' => '<p>Hidden pane</p>'] )],
            ['did' => 2, 'panel' => 'header', 'type' => 'custom', 'subtype' => 'custom', 'position' => 5, 'configuration' => serialize( ['body' => '<p>Default pane</p>', 'format' => 'filtered_html'] )],
            ['did' => 2, 'panel' => 'middle', 'type' => 'node_content', 'subtype' => 'node_content', 'position' => 1, 'configuration' => serialize( [] )],
            ['did' => 3, 'panel' => 'middle', 'type' => 'custom', 'subtype' => 'custom', 'position' => 0, 'configuration' => serialize( ['body' => '<p>Teaser pane</p>'] )],
            ['did' => 4, 'panel' => 'middle', 'type' => 'custom', 'subtype' => 'custom', 'position' => 0, 'configuration' => serialize( ['body' => '<p>Jobs pane</p>'] )],
        ] );
        $db->table( 'panels_pane' )->where( 'configuration', serialize( ['body' => '<p>Hidden pane</p>'] ) )->update( ['shown' => 0] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--types' => 'page,article'] ) );
        $this->assertSame( 1, substr_count( Artisan::output(), 'Skipped panel panes of type "views:news", not supported' ) );
        $queries = $this->queries( $db )->pluck( 'query' )->all();
        $db->disableQueryLog();

        // the panes of the default display and of all nodes are fetched once, also of displays without panes
        $this->assertCount( 1, array_filter( $queries, fn( $sql ) => str_contains( $sql, 'from "panels_pane" where "did" = ?' ) ) );
        $this->assertCount( 1, array_filter( $queries, fn( $sql ) => str_contains( $sql, 'from "panels_pane" where "did" in' ) ) );
        $this->assertStringContainsString( 'Jobs pane', $this->content( PageView::where( 'name', 'Jobs' )->firstOrFail() ) );

        $about = $this->content( PageView::where( 'name', 'About' )->firstOrFail() );
        $team = $this->content( PageView::where( 'name', 'Team' )->firstOrFail() );
        $plain = $this->content( PageView::where( 'name', 'Plain' )->firstOrFail() );

        // the panes of the node, regions in the order of Layout Builder
        $this->assertStringContainsString( 'Sidebar', $about );
        $this->assertStringContainsString( 'Pane text', $about );
        $this->assertStringContainsString( 'Hello', $about );
        $this->assertStringNotContainsString( 'About intro', $about );
        $this->assertStringNotContainsString( 'Hidden pane', $about );
        $this->assertStringNotContainsString( 'Teaser pane', $about );
        $this->assertLessThan( strpos( $about, 'Pane text' ), strpos( $about, 'Hello' ) );

        // the default panes of the content type
        $this->assertStringContainsString( 'Default pane', $team );
        $this->assertLessThan( strpos( $team, 'Team text' ), strpos( $team, 'Default pane' ) );
        $this->assertLessThan( strpos( $team, 'Team intro' ), strpos( $team, 'Team text' ) );

        // content types without Panelizer
        $this->assertStringContainsString( 'Plain text', $plain );
    }


    public function testImportDomainAccess(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'domain', function( Blueprint $table ) {
            $table->increments( 'domain_id' );
            $table->string( 'subdomain' );
            $table->string( 'sitename' );
            $table->string( 'machine_name' );
        } );
        $schema->create( 'domain_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'gid' );
            $table->string( 'realm' );
        } );
        $schema->create( 'domain_conf', function( Blueprint $table ) {
            $table->unsignedInteger( 'domain_id' );
            $table->binary( 'settings' );
        } );
        $this->tables( 'node_access' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Team'],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Legal'],
        ] );
        $db->table( 'url_alias' )->insert( [['source' => 'node/2', 'alias' => 'team'], ['source' => 'node/3', 'alias' => 'legal']] );
        $db->table( 'domain' )->insert( [
            ['domain_id' => 1, 'subdomain' => 'a.example.com', 'sitename' => 'Site A', 'machine_name' => 'site_a'],
            ['domain_id' => 2, 'subdomain' => 'b.example.com', 'sitename' => 'Site B', 'machine_name' => 'site_b'],
        ] );
        $db->table( 'domain_access' )->insert( [
            ['nid' => 1, 'gid' => 1, 'realm' => 'domain_id'],
            ['nid' => 2, 'gid' => 2, 'realm' => 'domain_id'],
            ['nid' => 3, 'gid' => 0, 'realm' => 'domain_site'],
        ] );
        $db->table( 'domain_conf' )->insert( ['domain_id' => 2, 'settings' => serialize( ['site_frontpage' => 'node/2'] )] );
        $db->table( 'node_access' )->insert( [
            ['nid' => 1, 'gid' => 1, 'realm' => 'domain_id'],
            ['nid' => 2, 'gid' => 2, 'realm' => 'domain_id'],
            ['nid' => 3, 'gid' => 0, 'realm' => 'domain_site'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertStringContainsString( 'Drupal uses the Domain Access module', Artisan::output() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_c'] ) );
        $this->assertStringContainsString( 'Domain Access domain "site_c" not found.', Artisan::output() );
        $this->assertSame( 0, PageView::count() );

        $queries = [];
        $db->listen( function( $query ) use ( &$queries ) { $queries[] = $this->sql( $query->sql ); } );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_a'] ) );
        $this->assertNotEmpty( preg_grep( '/^select "nid", "vid", "type", "title", "uid", "status", "promote", "sticky", "created", "changed" from "node" where .*"nid" in \(select "nid" from "domain_access"/', $queries ) );
        $this->assertStringContainsString( 'Using the hostname of the Domain Access domain as --domain=a.example.com', Artisan::output() );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => '2', '--domain' => 'b.example.com'] ) );

        $names = fn( string $domain, string $tag ) => PageView::where( 'domain', $domain )->where( 'tag', $tag )->orderBy( 'name' )->pluck( 'name' )->all();

        $this->assertSame( ['About', 'Legal'], $names( 'a.example.com', 'page' ) );
        $this->assertSame( ['Team'], $names( 'b.example.com', 'root' ) );
        $this->assertSame( ['Legal'], $names( 'b.example.com', 'page' ) );
        $this->assertSame( 0, PageView::where( 'domain', 'a.example.com' )->where( 'name', 'Team' )->count() );
        $this->assertNull( PageView::where( 'domain', 'b.example.com' )->where( 'name', 'Legal' )->firstOrFail()->accessValues() );
    }


    public function testImportContribFieldTypes(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'users' );
        $schema->create( 'bean', function( Blueprint $table ) {
            $table->increments( 'bid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'delta' );
            $table->string( 'label' );
            $table->string( 'title' );
            $table->string( 'type' );
            $table->unsignedInteger( 'changed' )->default( 1700000000 );
        } );
        $this->tables( 'block_custom' );

        $this->field( 'field_phone', 'telephone', ['value'], [], ['display' => ['default' => ['weight' => 1, 'label' => 'hidden']]] );
        $this->field( 'field_fax', 'phone', ['value'], [], ['display' => ['default' => ['weight' => 2, 'label' => 'hidden']]] );
        $this->field( 'field_owner', 'user_reference', ['uid'], [], ['display' => ['default' => ['weight' => 3, 'label' => 'hidden']]] );
        $this->field( 'field_total', 'computed', ['value'], [], ['display' => ['default' => ['weight' => 4, 'label' => 'hidden']]] );
        $this->field( 'field_clip', 'youtube', ['input', 'video_id'], [], ['display' => ['default' => ['weight' => 5]]] );
        $this->field( 'field_teaser', 'youtube', ['input', 'video_id'], [], ['display' => ['default' => ['weight' => 6]]] );
        $this->field( 'field_box', 'entityreference', ['target_id'], ['target_type' => 'bean'], ['display' => ['default' => ['weight' => 7]]] );
        $this->field( 'field_hours', 'text_long', ['value', 'format'], [], ['display' => ['default' => ['label' => 'hidden']]], 'basic', 'bean' );

        $db = DB::connection( 'drupal' );
        $db->table( 'users' )->insert( ['uid' => 5, 'name' => 'jdoe'] );
        $db->table( 'block_custom' )->insert( ['bid' => 3, 'body' => '<p>Custom</p>', 'info' => 'Custom', 'format' => 'filtered_html'] );
        $db->table( 'bean' )->insert( ['bid' => 1, 'vid' => 1, 'delta' => 'hours', 'label' => 'Hours', 'title' => '', 'type' => 'basic'] );
        $db->table( 'field_data_field_hours' )->insert( $this->row( 'field_hours', 1, ['value' => '<p>Mo-Fr 9-5</p>', 'format' => 'filtered_html'], 'und', 0, 'basic', 'bean' ) );
        $db->table( 'field_data_field_phone' )->insert( $this->row( 'field_phone', 1, ['value' => '+49 30 1234'] ) );
        $db->table( 'field_data_field_fax' )->insert( $this->row( 'field_fax', 1, ['value' => '+49 30 5678'] ) );
        $db->table( 'field_data_field_owner' )->insert( $this->row( 'field_owner', 1, ['uid' => 5] ) );
        $db->table( 'field_data_field_total' )->insert( $this->row( 'field_total', 1, ['value' => 'Total <42>'] ) );
        $db->table( 'field_data_field_clip' )->insert( $this->row( 'field_clip', 1, ['input' => 'https://youtu.be/ignored', 'video_id' => 'abcdef123'] ) );
        $db->table( 'field_data_field_teaser' )->insert( $this->row( 'field_teaser', 1, ['input' => 'https://vimeo.com/4711', 'video_id' => ''] ) );
        $db->table( 'field_data_field_box' )->insert( $this->row( 'field_box', 1, ['target_id' => 1] ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();
        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );

        // phone numbers, users and computed values are texts, YouTube fields videos and referenced beans their blocks
        $this->assertStringContainsString( '+49 30 1234', $content );
        $this->assertStringContainsString( '+49 30 5678', $content );
        $this->assertStringContainsString( 'jdoe', $content );
        $this->assertStringContainsString( 'Total &lt;42&gt;', $content );
        $this->assertStringContainsString( 'youtube-nocookie.com/embed/abcdef123', $content );
        $this->assertStringNotContainsString( 'ignored', $content );
        $this->assertStringContainsString( 'player.vimeo.com/video/4711', $content );
        $this->assertStringContainsString( '"refid":"' . Element::where( 'name', 'Hours' )->sole()->id . '"', $content );
        $this->assertStringContainsString( 'Mo-Fr 9-5', (string) json_encode( Element::where( 'name', 'Hours' )->sole()->data ) );
        $this->assertSame( 0, Element::where( 'name', 'Custom' )->count() );
        $this->assertStringNotContainsString( 'not supported', $output );
    }


    public function testImportWebformWarning(): void
    {
        Schema::connection( 'drupal' )->create( 'webform_component', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'cid' );
            $table->string( 'form_key' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'title' => 'Contact', 'status' => 1],
            ['nid' => 3, 'vid' => 3, 'type' => 'page', 'title' => 'Survey', 'status' => 1],
        ] );
        $db->table( 'webform_component' )->insert( [
            ['nid' => 3, 'cid' => 1, 'form_key' => 'name'],
            ['nid' => 3, 'cid' => 2, 'form_key' => 'email'],
            ['nid' => 2, 'cid' => 1, 'form_key' => 'message'],
            ['nid' => 9, 'cid' => 1, 'form_key' => 'other'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // the texts of webform nodes are imported, their forms can't be
        $this->assertStringContainsString( 'Skipped the forms of 2 Webform nodes (e.g. node 2), only their texts are imported', Artisan::output() );
        $this->assertSame( 1, PageView::where( 'title', 'Survey' )->count() );
    }


    public function testImportFieldTypesAndGroups(): void
    {
        $this->tables( 'users', 'field_group' );

        $this->field( 'field_address', 'addressfield', ['country', 'administrative_area', 'locality', 'postal_code', 'thoroughfare', 'premise', 'organisation_name', 'name_line', 'first_name', 'last_name'],
            [], ['display' => ['default' => ['weight' => 20]]] );
        $this->field( 'field_video', 'video_embed_field', ['video_url'], [], ['display' => ['default' => ['weight' => 1]]] );
        $this->field( 'field_author', 'entityreference', ['target_id'], ['target_type' => 'user'], ['display' => ['default' => ['weight' => 21]]] );
        $this->field( 'field_contact', 'addressfield', ['country', 'locality', 'name_line', 'first_name', 'last_name'], [], ['display' => ['default' => ['weight' => 30]]] );

        $db = DB::connection( 'drupal' );
        $db->table( 'users' )->insert( ['uid' => 5, 'name' => 'jdoe'] );
        $db->table( 'field_data_field_address' )->insert( $this->row( 'field_address', 1, [
            'country' => 'US', 'administrative_area' => 'IL', 'locality' => 'Springfield', 'postal_code' => '12345', 'thoroughfare' => 'Main St 1',
            'premise' => 'Suite 2', 'organisation_name' => 'ACME', 'name_line' => 'Ignored', 'first_name' => 'Jane', 'last_name' => 'Doe',
        ] ) );
        $db->table( 'field_data_field_contact' )->insert( $this->row( 'field_contact', 1, ['country' => 'DE', 'locality' => 'Berlin', 'name_line' => 'Max Muster', 'first_name' => '', 'last_name' => ''] ) );
        $db->table( 'field_data_field_video' )->insert( $this->row( 'field_video', 1, ['video_url' => 'https://www.youtube.com/watch?v=abcdef123'] ) );
        $db->table( 'field_data_field_author' )->insert( $this->row( 'field_author', 1, ['target_id' => 5] ) );
        $db->table( 'field_group' )->insert( [
            ['group_name' => 'group_info', 'entity_type' => 'node', 'bundle' => 'page', 'mode' => 'default', 'parent_name' => '',
                'data' => serialize( ['weight' => -5, 'children' => ['field_address', 'group_person']] )],
            ['group_name' => 'group_person', 'entity_type' => 'node', 'bundle' => 'page', 'mode' => 'default', 'parent_name' => 'group_info',
                'data' => serialize( ['weight' => -10, 'children' => ['field_author']] )],
            ['group_name' => 'group_form', 'entity_type' => 'node', 'bundle' => 'page', 'mode' => 'form', 'parent_name' => '',
                'data' => serialize( ['weight' => 100, 'children' => ['field_video']] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = $this->content( PageView::where( 'title', 'About' )->firstOrFail() );

        $this->assertStringContainsString( 'Jane Doe<br />ACME<br />Main St 1<br />Suite 2<br />12345 Springfield<br />IL<br />US', $content );
        $this->assertStringContainsString( 'Max Muster<br />Berlin<br />DE', $content );
        $this->assertStringNotContainsString( 'Ignored', $content );
        $this->assertStringContainsString( 'youtube-nocookie.com/embed/abcdef123', $content );
        $this->assertStringContainsString( 'jdoe', $content );

        // fields in groups are shown at the position of their group
        $this->assertLessThan( strpos( $content, 'Jane Doe' ), strpos( $content, 'jdoe' ) );
        $this->assertLessThan( strpos( $content, 'Welcome' ), strpos( $content, 'Jane Doe' ) );
        $this->assertLessThan( strpos( $content, 'youtube' ), strpos( $content, 'Welcome' ) );
        $this->assertLessThan( strpos( $content, 'Max Muster' ), strpos( $content, 'youtube' ) );
    }


    public function testImportRedirectOptions(): void
    {
        Schema::connection( 'drupal' )->create( 'redirect', function( Blueprint $table ) {
            $table->increments( 'rid' );
            $table->string( 'source' );
            $table->binary( 'source_options' );
            $table->string( 'redirect' );
            $table->binary( 'redirect_options' );
            $table->string( 'language' )->default( 'und' );
            $table->integer( 'status_code' )->default( 0 );
        } );

        DB::connection( 'drupal' )->table( 'redirect' )->insert( [
            ['source' => 'old', 'source_options' => serialize( [] ), 'redirect' => 'node/1', 'redirect_options' => serialize( ['query' => ['a' => 'b'], 'fragment' => 'top'] )],
            ['source' => 'search', 'source_options' => serialize( ['query' => ['q' => 'x']] ), 'redirect' => 'node/1', 'redirect_options' => serialize( [] )],
            ['source' => 'plain', 'source_options' => serialize( [] ), 'redirect' => 'https://example.org/', 'redirect_options' => serialize( ['fragment' => 'x'] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped 1 redirects with query strings in their source URL', Artisan::output() );

        $redirects = $this->redirects();

        $this->assertSame( '/about?a=b#top', $redirects['old'] ?? null );
        $this->assertSame( 'https://example.org/#x', $redirects['plain'] ?? null );
        $this->assertArrayNotHasKey( 'search', $redirects );
    }


    public function testDrupal7DbLocalesOfMissingTextGroup(): void
    {
        $this->locales();
        $this->locale( 'menu', 'item:1:title', 'Home', ['de' => 'Start'] );

        $db = DB::connection( 'drupal' );
        $d7 = new Drupal7Db( $db );

        $db->enableQueryLog();
        $blocks = [$d7->locales( 'blocks', ['block:1:body'] ), $d7->locales( 'blocks', ['block:2:body'] )];
        $menu = [$d7->locales( 'menu', ['item:1:title'] ), $d7->locales( 'menu', ['item:2:title'] )];
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        $this->assertSame( [[], []], $blocks );
        $this->assertSame( [['item:1:title' => ['de' => 'Start']], []], $menu );

        // without i18n_string, the strings of text groups without strings aren't searched for each call
        $this->assertCount( 2, $queries->filter( fn( $query ) => str_contains( $query, ' from "locales_source" where "textgroup" = ?' ) ) );
        $this->assertCount( 2, $queries->filter( fn( $query ) => str_contains( $query, ' from "locales_source" as "s"' ) ) );
    }


    public function testDrupal7DbHelpers(): void
    {
        $this->locales();
        $this->locale( 'menu', 'item:1:title', 'Home', ['de' => 'Start', 'fr' => ' '] );

        $db = DB::connection( 'drupal' );
        $db->table( 'variable' )->insert( ['name' => 'disabled', 'value' => serialize( false )] );
        $d7 = new Drupal7Db( $db );

        $this->assertSame( ['item:1:title' => ['de' => 'Start']], $d7->locales( 'menu', ['item:1:title'] ) );
        $this->assertSame( [], $d7->locales( 'menu', [] ) );

        // the strings are looked up in the indexed table of the i18n_string module if available
        Schema::connection( 'drupal' )->create( 'i18n_string', function( Blueprint $table ) {
            $table->unsignedInteger( 'lid' )->primary();
            $table->string( 'textgroup' );
            $table->string( 'context' );
            $table->index( ['textgroup', 'context'] );
        } );
        $db->table( 'i18n_string' )->insert( ['lid' => 1, 'textgroup' => 'menu', 'context' => 'item:1:title'] );
        $db->table( 'locales_source' )->where( 'lid', 1 )->update( ['context' => ''] );
        $d7 = new Drupal7Db( $db );

        $this->assertSame( ['item:1:title' => ['de' => 'Start']], $d7->locales( 'menu', ['item:1:title'] ) );
        $this->assertSame( [], $d7->locales( 'blocks', ['item:1:title'] ) );
        $this->assertNull( $d7->variable( 'disabled' ) );
        $this->assertSame( 'Example', $d7->variable( 'site_name' ) );
        $this->assertSame( 'en', $d7->langcode() );
        $this->assertSame( ['anonymous', 'authenticated', 'site_editor', 'role_4'], [$d7->role( 1, 'x' ), $d7->role( 2, 'x' ), $d7->role( 3, 'Site Editor' ), $d7->role( 4, '' )] );
        // duplicate IDs are fetched only once
        $calls = [];
        Database::chunked( [1, 1, 2], function( array $ids ) use ( &$calls ) {
            $calls[] = $ids;
            return collect();
        } );
        $this->assertSame( [[1, 2]], $calls );
        $this->assertSame( ['a' => 1], Database::unserializeArray( serialize( ['a' => 1] ) ) );
        $this->assertSame( [], Database::unserializeArray( serialize( 'text' ) ) );
        $this->assertSame( [], Database::unserializeArray( 'invalid' ) );
        $this->assertSame( 'text', Database::unserialize( serialize( 'text' ) ) );
        $this->assertNull( Database::unserialize( serialize( false ) ) );
        $this->assertNull( Database::unserialize( 1 ) );
        $this->assertInstanceOf( \__PHP_Incomplete_Class::class, Database::unserialize( serialize( new \ArrayObject() ) ) );
        $this->assertTrue( $d7->column( 'node', 'tnid' ) );
        $this->assertFalse( $d7->column( 'node', 'missing' ) );
        $this->assertFalse( $d7->exists( 'missing' ) );

        // the default language is English without language in the language_default variable
        $db->table( 'variable' )->where( 'name', 'language_default' )->update( ['value' => serialize( (object) ['name' => 'English'] )] );
        $this->assertSame( 'en', ( new Drupal7Db( $db ) )->langcode() );

        $db->table( 'variable' )->where( 'name', 'language_default' )->delete();
        $this->assertSame( 'en', ( new Drupal7Db( $db ) )->langcode() );
    }


    public function testCommandsRegistered(): void
    {
        $commands = array_keys( Artisan::all() );

        $this->assertContains( 'cms:drupal-import', $commands );
        $this->assertNotContains( 'cms:drupal7-import', $commands );
    }


    /**
     * Creates the tables of the i18n_string module.
     */
    protected function locales(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'locales_source', function( Blueprint $table ) {
            $table->increments( 'lid' );
            $table->string( 'textgroup' );
            $table->string( 'context' );
            $table->text( 'source' );
        } );
        $schema->create( 'locales_target', function( Blueprint $table ) {
            $table->unsignedInteger( 'lid' );
            $table->string( 'language' );
            $table->text( 'translation' );
        } );
    }


    /**
     * Adds a string of the i18n_string module and its translations.
     *
     * @param array<string, string> $translations Translations by language code
     */
    protected function locale( string $group, string $context, string $source, array $translations ): void
    {
        $db = DB::connection( 'drupal' );
        $lid = $db->table( 'locales_source' )->insertGetId( ['textgroup' => $group, 'context' => $context, 'source' => $source] );

        foreach( $translations as $language => $translation ) {
            $db->table( 'locales_target' )->insert( ['lid' => $lid, 'language' => $language, 'translation' => $translation] );
        }
    }


    /**
     * Returns the logged queries with the identifiers quoted like in SQLite and PostgreSQL.
     *
     * @return Collection<int, array{query: string, bindings: array<mixed>, time: float|null}>
     */
    protected function queries( \Illuminate\Database\Connection $db ): Collection
    {
        return Collection::make( $db->getQueryLog() )->map( fn( $query ) => ['query' => $this->sql( $query['query'] )] + $query );
    }


    /**
     * Returns the SQL statement with the identifiers quoted like in SQLite and PostgreSQL.
     *
     * MariaDB and MySQL quote identifiers with backticks, SQL Server with brackets and uses "top" instead of "limit".
     */
    protected function sql( string $sql ): string
    {
        return (string) preg_replace( '/^select top \d+ /', 'select ', strtr( $sql, ['`' => '"', '[' => '"', ']' => '"'] ) );
    }


    /**
     * Returns the imported redirects by source path.
     *
     * @return array<string, string>
     */
    protected function redirects(): array
    {
        return PageView::where( 'to', '!=', '' )->pluck( 'to', 'path' )
            ->mapWithKeys( fn( $to, $path ) => [ltrim( (string) $path, '/' ) => (string) $to] )->all();
    }


    /**
     * Creates the Drupal tables used by several tests.
     */
    private function tables( string ...$names ): void
    {
        $columns = [
            'block' => function( Blueprint $table ) {
                $table->increments( 'bid' );
                $table->string( 'module' );
                $table->string( 'delta' );
                $table->string( 'theme' );
                $table->integer( 'status' );
                $table->integer( 'weight' )->default( 0 );
                $table->string( 'region' );
                $table->integer( 'visibility' )->default( 0 );
                $table->text( 'pages' );
            },
            'block_custom' => function( Blueprint $table ) {
                $table->increments( 'bid' );
                $table->text( 'body' );
                $table->string( 'info' );
                $table->string( 'format' );
            },
            'entity_translation' => function( Blueprint $table ) {
                $table->string( 'entity_type' );
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'language' );
                $table->integer( 'status' )->default( 1 );
                $table->unsignedInteger( 'created' )->default( 0 );
                $table->unsignedInteger( 'changed' )->default( 0 );
            },
            'field_collection_item' => function( Blueprint $table ) {
                $table->unsignedInteger( 'item_id' );
                $table->unsignedInteger( 'revision_id' );
                $table->string( 'field_name' );
                $table->integer( 'archived' )->default( 0 );
            },
            'field_group' => function( Blueprint $table ) {
                $table->increments( 'id' );
                $table->string( 'group_name' );
                $table->string( 'entity_type' );
                $table->string( 'bundle' );
                $table->string( 'mode' );
                $table->string( 'parent_name' )->default( '' );
                $table->binary( 'data' );
            },
            'node_access' => function( Blueprint $table ) {
                $table->unsignedInteger( 'nid' );
                $table->unsignedInteger( 'gid' );
                $table->string( 'realm' );
                $table->unsignedInteger( 'grant_view' )->default( 1 );
            },
            'node_revision' => function( Blueprint $table ) {
                $table->unsignedInteger( 'nid' );
                $table->unsignedInteger( 'vid' );
                $table->string( 'title' );
                $table->unsignedInteger( 'timestamp' )->default( 1700000000 );
            },
            'role' => function( Blueprint $table ) {
                $table->increments( 'rid' );
                $table->string( 'name' );
            },
            'taxonomy_index' => function( Blueprint $table ) {
                $table->unsignedInteger( 'nid' );
                $table->unsignedInteger( 'tid' );
                $table->integer( 'sticky' )->default( 0 );
                $table->unsignedInteger( 'created' )->default( 0 );
            },
            'taxonomy_vocabulary' => function( Blueprint $table ) {
                $table->increments( 'vid' );
                $table->string( 'machine_name' );
            },
            'users' => function( Blueprint $table ) {
                $table->increments( 'uid' );
                $table->string( 'name' );
            },
        ];

        foreach( $names as $name ) {
            Schema::connection( 'drupal' )->create( $name, $columns[$name] );
        }
    }
}
