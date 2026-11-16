<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Import\JoomlaFolders;
use Aimeos\Cms\Import\JoomlaKind;
use Aimeos\Cms\Import\JoomlaFieldLookups;
use Aimeos\Cms\Import\JoomlaFields;
use Aimeos\Cms\Import\JoomlaFieldTypes;
use Aimeos\Cms\Import\JoomlaLevels;
use Aimeos\Cms\Import\JoomlaMenuLink;
use Aimeos\Cms\Import\JoomlaMenuRules;
use Aimeos\Cms\Import\JoomlaHost;
use Aimeos\Cms\Import\JoomlaOrigin;
use Aimeos\Cms\Import\JoomlaReader;
use Aimeos\Cms\Import\JoomlaRoutePath;
use Aimeos\Cms\Import\JoomlaRun;
use Aimeos\Cms\Import\JoomlaWiring;
use Aimeos\Cms\Import\JoomlaSite;
use Aimeos\Cms\Import\JoomlaRoutes;
use Aimeos\Cms\Import\JoomlaRouteCategories;
use Aimeos\Cms\Import\JoomlaRouteOptions;
use Aimeos\Cms\Import\JoomlaArticles;
use Aimeos\Cms\Import\JoomlaHomeRoot;
use Aimeos\Cms\Import\JoomlaTranslationRoute;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Base class of the Joomla import tests with the Joomla test database and the helpers creating the import objects.
 */
abstract class JoomlaTestAbstract extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'database.connections.joomla', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection( 'joomla' );

        $schema->create( 'content', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'alias' );
            $table->text( 'introtext' );
            $table->text( 'fulltext' );
            $table->integer( 'state' )->default( 1 );
            $table->unsignedInteger( 'catid' )->default( 0 );
            $table->string( 'language' )->default( '*' );
            $table->text( 'images' )->default( '' );
            $table->string( 'metadesc' )->default( '' );
            $table->boolean( 'featured' )->default( false );
            $table->integer( 'ordering' )->default( 0 );
            $table->string( 'created' )->default( '0000-00-00 00:00:00' );
            $table->string( 'modified' )->default( '0000-00-00 00:00:00' );
        } );

        $schema->create( 'categories', function( Blueprint $table ) {
            $table->id();
            $table->string( 'extension' );
            $table->string( 'title' );
            $table->string( 'alias' );
            $table->integer( 'published' )->default( 1 );
            $table->unsignedInteger( 'parent_id' )->default( 0 );
            $table->unsignedInteger( 'level' )->default( 1 );
            $table->unsignedInteger( 'lft' )->default( 0 );
        } );

        $schema->create( 'menu', function( Blueprint $table ) {
            $table->id();
            $table->string( 'menutype' );
            $table->string( 'title' );
            $table->string( 'alias' );
            $table->string( 'link' );
            $table->string( 'type' );
            $table->integer( 'published' )->default( 1 );
            $table->unsignedInteger( 'client_id' )->default( 0 );
            $table->unsignedInteger( 'parent_id' )->default( 1 );
            $table->unsignedInteger( 'level' )->default( 1 );
            $table->unsignedInteger( 'lft' )->default( 0 );
        } );

        $schema->create( 'associations', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->string( 'context' );
            $table->string( 'key' );
        } );

        $schema->create( 'languages', function( Blueprint $table ) {
            $table->id( 'lang_id' );
            $table->string( 'lang_code' );
            $table->string( 'sef' );
            $table->integer( 'published' )->default( 1 );
        } );

        $db = DB::connection( 'joomla' );

        $db->table( 'languages' )->insert( [
            ['lang_code' => 'en-GB', 'sef' => 'en'],
            ['lang_code' => 'de-DE', 'sef' => 'de'],
        ] );

        $db->table( 'content' )->insert( [
            ['id' => 1, 'title' => 'About', 'alias' => 'about', 'language' => 'en-GB', 'introtext' => '<h2>Welcome</h2><p>Hello</p>', 'fulltext' => ''],
            ['id' => 2, 'title' => 'Über uns', 'alias' => 'ueber-uns', 'language' => 'de-DE', 'introtext' => '<h2>Willkommen</h2><p>Hallo</p>', 'fulltext' => ''],
            ['id' => 3, 'title' => 'À propos', 'alias' => 'a-propos', 'language' => 'fr-FR', 'introtext' => '<p>Bonjour</p>', 'fulltext' => ''],
            ['id' => 4, 'title' => 'Nur Deutsch', 'alias' => 'nur-deutsch', 'language' => 'de-DE', 'introtext' => '<p>Text</p>', 'fulltext' => ''],
        ] );

        $db->table( 'content' )->where( 'id', 1 )->update( ['created' => '2019-03-04 05:06:07'] );

        $db->table( 'associations' )->insert( [
            ['id' => 1, 'context' => 'com_content.item', 'key' => 'abc'],
            ['id' => 2, 'context' => 'com_content.item', 'key' => 'abc'],
            ['id' => 3, 'context' => 'com_content.item', 'key' => 'abc'],
        ] );
    }


    /**
     * Adds the access and publication columns of the articles, categories and menu items and the tables of the view levels.
     */
    protected function accessColumns(): void
    {
        $schema = Schema::connection( 'joomla' );

        foreach( ['content', 'menu'] as $name )
        {
            $schema->table( $name, function( Blueprint $table ) {
                $table->unsignedInteger( 'access' )->default( 1 );
                $table->string( 'publish_up' )->nullable();
                $table->string( 'publish_down' )->nullable();
            } );
        }

        $schema->table( 'categories', fn( Blueprint $table ) => $table->unsignedInteger( 'access' )->default( 1 ) );
        $schema->create( 'viewlevels', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'rules' );
        } );
        $schema->create( 'usergroups', function( Blueprint $table ) {
            $table->id();
            $table->unsignedInteger( 'parent_id' );
        } );
        $this->extensionsTable();
    }


    /**
     * Creates the optional tables of the Joomla test database and enables the extensions using them.
     *
     * Adds the menu item and article columns for the options, the custom fields with the field plugins,
     * the tags, the modules and the redirects.
     */
    protected function extraTables(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->text( 'params' )->nullable() );
        $schema->table( 'content', function( Blueprint $table ) {
            $table->text( 'attribs' )->nullable();
            $table->text( 'urls' )->nullable();
        } );
        $this->extensionsTable();
        DB::connection( 'joomla' )->table( 'extensions' )->insert( [
            ['type' => 'plugin', 'folder' => 'content', 'element' => 'fields', 'params' => '{}'],
            ['type' => 'plugin', 'folder' => 'system', 'element' => 'fields', 'params' => '{}'],
            ['type' => 'plugin', 'folder' => 'content', 'element' => 'loadmodule', 'params' => '{}'],
            ['type' => 'module', 'folder' => '', 'element' => 'mod_custom', 'params' => '{}'],
            ['type' => 'module', 'folder' => '', 'element' => 'mod_menu', 'params' => '{}'],
        ] );
        DB::connection( 'joomla' )->table( 'extensions' )->insert( array_map( fn( $type ) => ['type' => 'plugin', 'folder' => 'fields', 'element' => $type, 'params' => '{}'],
            ['calendar', 'checkboxes', 'color', 'editor', 'imagelist', 'integer', 'list', 'media', 'radio', 'sql', 'subform', 'text', 'textarea', 'url', 'user', 'usergrouplist'] ) );
        $schema->create( 'fields', function( Blueprint $table ) {
            $table->id();
            $table->string( 'context' );
            $table->string( 'title' );
            $table->string( 'label' );
            $table->string( 'type' );
            $table->text( 'params' );
            $table->text( 'fieldparams' );
            $table->integer( 'state' );
            $table->integer( 'ordering' );
            $table->unsignedInteger( 'access' );
            $table->string( 'language' );
            $table->unsignedInteger( 'group_id' );
            $table->text( 'default_value' )->default( '' );
            $table->integer( 'only_use_in_subform' )->default( 0 );
        } );
        $schema->create( 'fields_groups', function( Blueprint $table ) {
            $table->id();
            $table->integer( 'state' );
            $table->unsignedInteger( 'access' )->default( 1 );
        } );
        $schema->create( 'fields_categories', function( Blueprint $table ) {
            $table->unsignedInteger( 'field_id' );
            $table->unsignedInteger( 'category_id' );
        } );
        $schema->create( 'fields_values', function( Blueprint $table ) {
            $table->unsignedInteger( 'field_id' );
            $table->string( 'item_id' );
            $table->text( 'value' );
        } );
        $schema->create( 'tags', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->integer( 'published' );
            $table->unsignedInteger( 'access' );
            $table->unsignedInteger( 'lft' );
            $table->string( 'language' )->default( '*' );
        } );
        $schema->create( 'contentitem_tag_map', function( Blueprint $table ) {
            $table->string( 'type_alias' );
            $table->unsignedInteger( 'content_item_id' );
            $table->unsignedInteger( 'tag_id' );
        } );
        $schema->create( 'modules', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'module' );
            $table->string( 'position' );
            $table->text( 'content' );
            $table->integer( 'showtitle' )->default( 1 );
            $table->integer( 'ordering' );
            $table->integer( 'published' )->default( 1 );
            $table->unsignedInteger( 'client_id' )->default( 0 );
            $table->unsignedInteger( 'access' )->default( 1 );
            $table->text( 'params' )->nullable();
        } );
        $schema->create( 'redirect_links', function( Blueprint $table ) {
            $table->id();
            $table->string( 'old_url' );
            $table->string( 'new_url' );
            $table->integer( 'published' )->default( 1 );
            $table->integer( 'header' )->default( 301 );
        } );
    }


    /**
     * Creates the extra tables and adds meta descriptions to the languages and articles of the Joomla test database.
     *
     * Article 1 gets its own description, article 5 has none, so the description of its language is used.
     *
     * @return \Illuminate\Database\Connection Connection to the Joomla test database
     */
    protected function metaDescriptions(): \Illuminate\Database\Connection
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'languages', fn( Blueprint $table ) => $table->string( 'metadesc' )->default( '' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'languages' )->where( 'lang_code', 'en-GB' )->update( ['metadesc' => 'English site'] );
        $db->table( 'languages' )->where( 'lang_code', 'de-DE' )->update( ['metadesc' => 'Deutsche Seite'] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Any', 'alias' => 'any', 'language' => '*', 'introtext' => '<p>Any</p>', 'fulltext' => '', 'metadesc' => ''] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['metadesc' => 'Own'] );

        return $db;
    }


    /**
     * Creates the extensions table of the Joomla test database if it doesn't exist yet.
     *
     * The table contains all columns the reader uses, the optional ones can be left out to test databases without them.
     *
     * @param list<string> $without Optional columns which aren't created: "client_id", "state", "access" or "manifest_cache"
     */
    protected function extensionsTable( array $without = [] ): void
    {
        $schema = Schema::connection( 'joomla' );

        if( $schema->hasTable( 'extensions' ) ) {
            return;
        }

        $schema->create( 'extensions', function( Blueprint $table ) use ( $without ) {
            $table->string( 'type' )->default( 'component' );
            $table->string( 'folder' )->default( '' );
            $table->string( 'element' );
            $table->integer( 'enabled' )->default( 1 );
            $table->text( 'params' )->nullable();

            $optional = [
                'client_id' => fn() => $table->unsignedInteger( 'client_id' )->default( 0 ),
                'state' => fn() => $table->integer( 'state' )->default( 0 ),
                'access' => fn() => $table->unsignedInteger( 'access' )->nullable()->default( 1 ),
                'manifest_cache' => fn() => $table->text( 'manifest_cache' )->nullable(),
            ];

            foreach( array_diff_key( $optional, array_flip( $without ) ) as $column ) {
                $column();
            }
        } );
    }


    /**
     * Enables or disables the "System - Language Filter" plugin in the extensions table.
     *
     * The extensions table is created if it doesn't exist yet.
     *
     * The plugin is added with its default options, so the item associations are on.
     *
     * @param bool $enabled TRUE to enable the plugin, FALSE to remove it
     */
    protected function languageFilter( bool $enabled = true ): void
    {
        $this->extensionsTable();

        $plugin = ['type' => 'plugin', 'folder' => 'system', 'element' => 'languagefilter'];
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->where( $plugin )->delete();

        if( $enabled ) {
            $db->table( 'extensions' )->insert( $plugin + ['params' => '{}'] );
        }
    }


    /**
     * Stores the major Joomla version in the extensions table or removes it, so the version is unknown.
     *
     * The extensions table is created if it doesn't exist yet.
     *
     * @param int|null $version Major Joomla version or NULL to remove the version
     */
    protected function setJoomlaVersion( ?int $version ): void
    {
        $this->extensionsTable();

        $files = ['type' => 'file', 'element' => 'joomla'];
        $db = DB::connection( 'joomla' );

        $db->table( 'extensions' )->where( $files )->delete();

        if( $version !== null ) {
            $db->table( 'extensions' )->insert( $files + ['params' => '{}', 'manifest_cache' => json_encode( ['version' => $version . '.0.0'] )] );
        }
    }


    /**
     * Returns the reader of the Joomla test database.
     *
     * @param Connection|null $db Database connection, the Joomla test database if NULL
     * @param string $prefix Prefix of the Joomla tables
     * @param \Closure|null $warn Receives the warnings of the reader, they are ignored if NULL
     */
    protected function reader( ?Connection $db = null, string $prefix = '', ?\Closure $warn = null ): JoomlaReader
    {
        return new JoomlaReader( $db ?? DB::connection( 'joomla' ), $prefix, $warn ?? fn() => null );
    }


    /**
     * Returns the records like the ones of the Joomla reader.
     *
     * @param array<int|string, \stdClass> $items Records
     * @return Collection<int, \stdClass>
     */
    protected function items( array $items ): Collection
    {
        return new Collection( array_values( $items ) );
    }


    /**
     * Returns the categories like the ones of the Joomla reader.
     *
     * @param array<int, \stdClass> $categories Categories by ID
     * @return Collection<int, \stdClass>
     */
    protected function categories( array $categories ): Collection
    {
        return new Collection( $categories );
    }


    /**
     * Returns the record of a menu item of the main menu linking to an article or of a menu heading.
     *
     * @param int $id ID of the menu item, also used as its position
     * @param string $title Title of the menu item, its lower case version is the alias
     * @param int $parentId ID of the parent menu item
     * @param int $level Level of the menu item in the menu tree
     * @param int|null $articleId ID of the linked article or NULL for a menu heading
     * @return array<string, int|string>
     */
    protected function menuItem( int $id, string $title, int $parentId, int $level, ?int $articleId = null ): array
    {
        return ['id' => $id, 'menutype' => 'mainmenu', 'title' => $title, 'alias' => strtolower( $title ),
            'link' => $articleId ? "index.php?option=com_content&view=article&id={$articleId}" : '',
            'type' => $articleId ? 'component' : 'heading', 'parent_id' => $parentId, 'level' => $level, 'lft' => $id];
    }


    /**
     * Returns the settings of the Joomla site read from the Joomla test database like the import does.
     *
     * The language filter and the Joomla version are set by languageFilter() and setJoomlaVersion() before.
     *
     * @param Collection<array-key, \stdClass> $categories Categories of all states by ID
     * @param array<int|string, mixed> $options Joomla language tags imported as translations with their codes and optional URLs like "de-DE:de"
     * @param string $lang Language code of the imported pages
     * @param JoomlaReader|null $reader Reader for the Joomla database, a new one if NULL
     */
    protected function site( Collection $categories, array $options = [], string $lang = 'en', ?JoomlaReader $reader = null ): JoomlaSite
    {
        return new JoomlaSite( $reader ?? $this->reader(), $categories, $options, $lang );
    }


    /**
     * Returns the objects importing the Joomla site like the import creates them.
     *
     * @param JoomlaSite $site Settings of the Joomla site
     * @param Collection<int, \stdClass>|null $items Menu items of all menus, none if NULL
     * @param Collection<int, \stdClass>|null $menu Menu items of the imported menu, none if NULL
     * @param JoomlaOrigin|null $origin Domain and file base of the imported site, none if NULL
     * @param JoomlaHost|null $host Functions and settings of the import command, ones doing nothing if NULL
     * @param JoomlaReader|null $reader Reader for the Joomla database, a new one if NULL
     */
    protected function wiring( JoomlaSite $site, ?Collection $items = null, ?Collection $menu = null, ?JoomlaOrigin $origin = null,
        ?JoomlaHost $host = null, ?JoomlaReader $reader = null ): JoomlaWiring
    {
        return new JoomlaWiring( $reader ?? $this->reader(), $host ?? JoomlaHost::forTests(), $origin ?? new JoomlaOrigin(), $site,
            $menu ?? Collection::make(), $items ?? Collection::make() );
    }


    /**
     * Returns the objects building the pages like the import creates them.
     *
     * Use named arguments for everything after the site or wiring. Tests with menu items or an origin pass the wiring
     * returned by wiring(), tests which also need the router, the menu or the category items keep the wiring themselves.
     *
     * @param JoomlaSite|JoomlaWiring $from Settings of the Joomla site without menu items and origin or the objects importing it
     * @param JoomlaArticles|null $articles Imported articles, none if NULL
     * @param JoomlaFolders $folders Categories and menu items imported as folders, none by default
     * @param JoomlaHomeRoot $home Article imported into the root page with its translations, none by default
     * @param iterable<\stdClass> $redirects Joomla redirects
     */
    protected function importRun( JoomlaSite|JoomlaWiring $from, ?JoomlaArticles $articles = null, JoomlaFolders $folders = new JoomlaFolders(),
        JoomlaHomeRoot $home = new JoomlaHomeRoot(), iterable $redirects = [] ): JoomlaRun
    {
        $wiring = $from instanceof JoomlaWiring ? $from : $this->wiring( $from );
        return $wiring->assemble( articles: $articles ?? $this->articles(), folders: $folders, home: $home, redirects: $redirects );
    }


    /**
     * Returns the imported articles with only the URL paths Joomla uses for them and their translations.
     *
     * @param array<int|string, JoomlaRoutePath> $routes Routes of the articles by article ID
     * @param array<int|string, JoomlaTranslationRoute> $translations Routes of the translations by article ID
     * @param array<int|string, int> $categoryIds Category IDs of the articles imported as pages by article ID, their categories get own pages
     * @param array<int|string, list<int|string>> $translated IDs of the translations by article ID
     */
    protected function articles( array $routes = [], array $translations = [], array $categoryIds = [], array $translated = [] ): JoomlaArticles
    {
        return new JoomlaArticles( categoryIds: $categoryIds, translations: $translated, times: [], items: [], visible: [],
            routes: [JoomlaKind::ARTICLE => $routes, JoomlaKind::ARTICLE_TRANSLATION => $translations] );
    }


    /**
     * Returns the routes of the categories and menu items.
     *
     * @param Collection<array-key, \stdClass> $categories Categories Joomla routes by ID
     * @param iterable<\stdClass> $items Public menu items ordered by their position in the menu
     * @param JoomlaRouteOptions $options Router options, the modern router with IDs and without strict routing by default
     * @param JoomlaMenuRules $rules Menu rules, the ones with language filter by default
     */
    protected function routes( Collection $categories, iterable $items, JoomlaRouteOptions $options = new JoomlaRouteOptions( noIds: false, strict: false ),
        JoomlaMenuRules $rules = new JoomlaMenuRules( true ) ): JoomlaRoutes
    {
        return new JoomlaRoutes( new JoomlaRouteCategories( $categories ), $items, $options, $rules );
    }


    /**
     * Returns the kind of the menu item.
     *
     * @return string Kind from JoomlaMenuLink::of(), e.g. JoomlaMenuLink::KIND_ARTICLE
     */
    protected function kindOf( \stdClass $item ): string
    {
        return JoomlaMenuLink::of( $item )->kind;
    }


    /**
     * Asserts the kind of the menu item and the ID of the article or category it shows.
     *
     * @param string $kind Expected kind from JoomlaMenuLink::of(), e.g. JoomlaMenuLink::KIND_ARTICLE
     * @param int|null $id Expected ID of the article or category or NULL if the menu item shows none
     * @param \stdClass $item Menu item
     */
    protected function assertMenuLink( string $kind, ?int $id, \stdClass $item ): void
    {
        $link = JoomlaMenuLink::of( $item );

        $this->assertSame( $kind, $link->kind, 'kind of ' . ( $item->link ?? '' ) );
        $this->assertSame( $id, $link->id, 'ID of ' . ( $item->link ?? '' ) );
    }


    /**
     * Returns the custom fields using the reader of the Joomla test database and its field plugins.
     *
     * Like JoomlaExtras::shownFields(), fields whose values aren't renderable() are skipped.
     * The fields are created like JoomlaWiring::fields() does, so they are rendered like Joomla 3 if the database is of Joomla 3.
     *
     * @param iterable<\stdClass> $list Fields ordered by their position
     * @param JoomlaOrigin $origin Domain and file base of the imported site, none by default
     * @param \DateTimeZone|null $timezone Time zone of the Joomla site, the one of the import command if NULL
     * @param JoomlaReader|null $reader Reader for the Joomla database, a new one if NULL
     */
    protected function fields( iterable $list, JoomlaOrigin $origin = new JoomlaOrigin(), ?\DateTimeZone $timezone = null, ?JoomlaReader $reader = null ): JoomlaFields
    {
        $reader ??= $this->reader();
        $list = collect( $list )->filter( JoomlaFields::renderable( ... ) );
        $site = $this->site( $this->categories( [] ), reader: $reader );

        $types = new JoomlaFieldTypes( $origin, ( JoomlaHost::forTests( timezone: $timezone ) )->timezone, $site->version );

        return new JoomlaFields( $list, $site->plugins, new JoomlaFieldLookups( $reader ), $types );
    }


    /**
     * Returns if guests can see the rows of the view levels.
     *
     * @param array<int, int> $ids View level IDs
     * @return array<int, bool>
     */
    protected function publicLevels( JoomlaLevels $levels, array $ids ): array
    {
        return array_map( fn( $id ) => $levels->isPublic( $id ), $ids );
    }
}
