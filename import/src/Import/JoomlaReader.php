<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;


/**
 * Reads the Joomla data the importer uses from a Joomla database.
 *
 * These are the articles and their texts, categories, menu items, languages, associations, custom fields
 * and their values, tags, modules, redirects, users, view levels and the extensions with their options.
 *
 * Records of whole tables the callers filter, like the categories, menu items or modules, are returned as collections.
 * Records fetched for given IDs and values grouped or mapped by ID, like the articles or the associations, are returned as arrays.
 */
class JoomlaReader
{
    /** Contexts of the associated articles, categories and menu items */
    public const ARTICLE_ASSOCIATIONS = JoomlaExtensions::COMPONENT_CONTENT . '.item';
    public const CATEGORY_ASSOCIATIONS = JoomlaExtensions::COMPONENT_CATEGORIES . '.item';
    public const MENU_ASSOCIATIONS = JoomlaExtensions::COMPONENT_MENUS . '.item';

    /** Types of the tagged articles and categories, unlike the field contexts the one of the categories is singular */
    public const ARTICLE_TAGS = JoomlaExtensions::COMPONENT_CONTENT . '.article';
    public const CATEGORY_TAGS = JoomlaExtensions::COMPONENT_CONTENT . '.category';

    /** Columns of the publishing dates of the articles, menu items and modules */
    protected const SCHEDULE_COLUMNS = ['publish_up', 'publish_down'];

    /** Columns of the articles used by the import */
    protected const ARTICLE_COLUMNS = ['id', 'title', 'alias', 'catid', 'created', 'modified', 'featured', 'language',
        'access', ...self::SCHEDULE_COLUMNS, 'metadesc', 'metakey', 'metadata'];

    /** Columns of the articles needed for all articles to order and group them and to make their URLs unique */
    protected const INDEX_COLUMNS = ['id', 'catid', 'language', 'alias', 'title'];

    /** Columns of the articles needed for all articles to find the changed ones */
    protected const TIME_COLUMNS = ['created', 'modified'];

    /** Columns of the articles fetched by texts() when they are imported */
    protected const TEXT_COLUMNS = ['id', 'introtext', 'fulltext', 'images', 'urls', 'attribs'];

    /** Columns of the categories used by the import, Joomla updates "modified_time" whenever a category is saved */
    protected const CATEGORY_COLUMNS = ['id', 'parent_id', 'level', 'title', 'alias', 'access', 'published', 'language',
        'description', 'params', 'metadesc', 'metakey', 'metadata', 'modified_time'];

    /** Columns of the menu items used by the import */
    protected const MENU_COLUMNS = ['id', 'menutype', 'parent_id', 'title', 'alias', 'link', 'type', 'access', ...self::SCHEDULE_COLUMNS,
        'home', 'language', 'params', 'path', 'lft', 'level'];

    /** Columns of the custom fields used by the import */
    protected const FIELD_COLUMNS = [
        'id', 'title', 'label', 'type', 'params', 'fieldparams', 'default_value', 'access', 'language', 'group_id', 'only_use_in_subform'
    ];

    /** Columns of the modules used by the import */
    protected const MODULE_COLUMNS = [
        'id', 'title', 'module', 'position', 'content', 'showtitle', 'params', 'access', 'language', ...self::SCHEDULE_COLUMNS
    ];

    /** Columns of the redirects used by the import */
    protected const REDIRECT_COLUMNS = ['id', 'old_url', 'new_url', 'header'];

    /** Columns of the content languages */
    protected const LANGUAGE_COLUMNS = ['lang_id', 'lang_code', 'sef', 'metadesc', 'access', 'published', 'ordering'];

    /** Names of the Joomla tables without prefix */
    protected const TABLE_CONTENT = 'content';
    protected const TABLE_CATEGORIES = 'categories';
    protected const TABLE_MENU = 'menu';
    protected const TABLE_USERGROUPS = 'usergroups';
    protected const TABLE_ASSOCIATIONS = 'associations';
    protected const TABLE_CONTENTITEM_TAG_MAP = 'contentitem_tag_map';
    protected const TABLE_FIELDS = 'fields';
    protected const TABLE_FIELDS_CATEGORIES = 'fields_categories';
    protected const TABLE_FIELDS_GROUPS = 'fields_groups';
    protected const TABLE_FIELDS_VALUES = 'fields_values';
    protected const TABLE_LANGUAGES = 'languages';
    protected const TABLE_MODULES = 'modules';
    protected const TABLE_MODULES_MENU = 'modules_menu';
    protected const TABLE_REDIRECT_LINKS = 'redirect_links';
    protected const TABLE_TAGS = 'tags';
    protected const TABLE_USERS = 'users';
    protected const TABLE_VIEWLEVELS = 'viewlevels';

    /** View levels, fetched once */
    protected ?JoomlaLevels $levels = null;
    /** @var Collection<int|string, \stdClass>|null Published menu items of the site by ID ordered by their position, fetched once */
    protected ?Collection $menuItems = null;
    /** Version, component options, enabled plugins and site languages of the Joomla installation */
    protected JoomlaExtensions $extensions;
    /** Existing Joomla tables and columns */
    protected JoomlaSchema $schema;
    /** Runs the queries of the custom SQL fields */
    protected JoomlaSqlOptions $sqlOptions;


    /**
     * @param Connection $db Connection to the Joomla database
     * @param string $prefix Joomla database table prefix, "#__" is removed
     * @param \Closure(string): void $warn Shows a warning, e.g. if the query of a custom SQL field is rejected or fails
     */
    public function __construct( protected Connection $db, string $prefix, \Closure $warn )
    {
        $this->schema = new JoomlaSchema( $db, $prefix );
        $this->extensions = new JoomlaExtensions( $db, $this->schema );
        $this->sqlOptions = new JoomlaSqlOptions( $db, $this->schema, $warn );
    }


    /**
     * Returns the version, the component options, the installed plugins and the site languages of the Joomla installation.
     */
    public function extensions(): JoomlaExtensions
    {
        return $this->extensions;
    }


    /**
     * Returns the object fetching the options of the custom SQL fields from the Joomla database.
     */
    public function sqlOptions(): JoomlaSqlOptions
    {
        return $this->sqlOptions;
    }


    /**
     * Fetches the IDs, categories, languages, aliases and titles of the published and archived Joomla articles one by one.
     *
     * Only these columns are kept for all articles, the others are fetched by articles() and texts() when the articles are imported.
     *
     * @param bool $withTimes TRUE to fetch the creation and modification times too
     * @return LazyCollection<int, \stdClass>
     */
    public function index( bool $withTimes = false ): LazyCollection
    {
        $columns = $withTimes ? [...self::INDEX_COLUMNS, ...self::TIME_COLUMNS] : self::INDEX_COLUMNS;

        // the published and archived articles Joomla shows on the site, ordered by their position in their category
        return $this->db
            ->table( $this->schema->table( self::TABLE_CONTENT ) )
            ->whereIn( 'state', JoomlaRow::VISIBLE_STATES )
            ->orderBy( 'ordering', 'asc' )
            ->orderBy( 'created', 'asc' )
            ->select( $this->schema->existingColumns( self::TABLE_CONTENT, $columns ) )
            ->cursor();
    }


    /**
     * Fetches the publishing dates of the published and archived Joomla articles which start or end within the time span.
     *
     * Joomla doesn't change the articles when they are published or expire at their publishing dates, so these dates
     * are changes of the articles too. Articles without publishing date columns have none.
     *
     * @param int $from Start of the time span as timestamp
     * @param int $to End of the time span as timestamp
     * @return array<int|string, int> Latest publishing date within the time span as timestamp by article ID
     */
    public function scheduled( int $from, int $to ): array
    {
        $times = [];
        $columns = $this->schema->existingColumns( self::TABLE_CONTENT, self::SCHEDULE_COLUMNS );

        if( $columns === [] || $from > $to ) {
            return $times;
        }

        // the dates are stored in UTC in the format "YYYY-MM-DD HH:MM:SS", so they are compared as strings
        $range = [gmdate( 'Y-m-d H:i:s', $from ), gmdate( 'Y-m-d H:i:s', $to )];
        $rows = $this->db->table( $this->schema->table( self::TABLE_CONTENT ) )
            ->whereIn( 'state', JoomlaRow::VISIBLE_STATES )
            ->where( function( $query ) use ( $columns, $range ) {
                foreach( $columns as $column ) {
                    $query->orWhereBetween( $column, $range );
                }
            } )
            ->select( ['id', ...$columns] )
            ->cursor();

        foreach( $rows as $row )
        {
            foreach( $columns as $column )
            {
                $time = JoomlaDate::time( $row->{$column} ?? null );

                if( $time !== null && $time >= $from && $time <= $to ) {
                    $times[$row->id] = max( $times[$row->id] ?? $time, $time );
                }
            }
        }

        return $times;
    }


    /**
     * Fetches the category IDs of the published and archived Joomla articles in the categories.
     *
     * @param array<int|string> $catIds IDs of the categories
     * @return array<int|string, int> Category IDs by article ID
     */
    public function categoryArticles( array $catIds ): array
    {
        $table = $this->schema->table( self::TABLE_CONTENT );
        $fetch = fn( $chunk ) => $this->db->table( $table )->whereIn( 'catid', $chunk )
            ->whereIn( 'state', JoomlaRow::VISIBLE_STATES )->get( ['id', 'catid'] );

        return Database::chunked( $catIds, $fetch )->mapWithKeys( fn( $row ) => [$row->id => JoomlaArticle::catId( $row )] )->all();
    }


    /**
     * Fetches the Joomla articles imported next without their texts.
     *
     * @param array<int|string> $ids IDs of the articles
     * @return array<int|string, \stdClass> Articles by ID
     */
    public function articles( array $ids ): array
    {
        return $this->fetchArticles( $ids, self::ARTICLE_COLUMNS );
    }


    /**
     * Fetches the texts of the Joomla articles.
     *
     * @param array<int|string> $ids IDs of the articles
     * @return array<int|string, \stdClass> Texts, images, links and options by article ID
     */
    public function texts( array $ids ): array
    {
        return $this->fetchArticles( $ids, self::TEXT_COLUMNS );
    }


    /**
     * Fetches the IDs of the associated articles, categories or menu items grouped by association key.
     *
     * @param string $context Joomla association context, e.g. "com_categories.item" for categories or "com_menus.item" for menu items
     * @return array<int|string, list<int|string>>
     */
    public function associations( string $context ): array
    {
        $groups = [];

        if( !( $table = $this->schema->tableIfExists( self::TABLE_ASSOCIATIONS ) ) ) {
            return $groups;
        }

        $rows = $this->db->table( $table )->where( 'context', $context )->select( ['id', 'key'] )->cursor();

        foreach( $rows as $row ) {
            $groups[$row->key][] = $row->id;
        }

        return $groups;
    }


    /**
     * Fetches the categories of all states keyed by id.
     *
     * The unpublished and trashed categories are needed to hide the articles and subcategories below them.
     *
     * @return Collection<array-key, \stdClass>
     */
    public function categories(): Collection
    {
        return $this->db
            ->table( $this->schema->table( self::TABLE_CATEGORIES ) )
            ->where( 'extension', JoomlaExtensions::COMPONENT_CONTENT )
            ->orderBy( 'lft', 'asc' )
            ->get( $this->schema->existingColumns( self::TABLE_CATEGORIES, self::CATEGORY_COLUMNS ) )
            ->keyBy( 'id' );
    }


    /**
     * Fetches the content languages of all states in the order Joomla uses them.
     *
     * @return Collection<int, \stdClass>
     */
    public function languages(): Collection
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_LANGUAGES ) ) ) {
            return Collection::make();
        }

        $columns = $this->schema->existingColumns( self::TABLE_LANGUAGES, self::LANGUAGE_COLUMNS );
        $query = $this->db->table( $table )->select( $columns );

        // like Joomla, the content languages are ordered by their position, the ones at the same position in the order they were added
        if( in_array( 'ordering', $columns, true ) ) {
            $query->orderBy( 'ordering' );
        }

        return $query->orderBy( 'lang_id' )->get();
    }


    /**
     * Fetches the published custom fields of the articles or categories ordered by their position.
     *
     * Fields of unpublished field groups aren't shown by Joomla and therefore skipped.
     * The access level of the field group is returned as "group_access", NULL for fields without group.
     *
     * @param string $context Context of the fields, JoomlaFields::ARTICLE_CONTEXT or JoomlaFields::CATEGORY_CONTEXT
     * @return Collection<int, \stdClass>
     */
    public function fields( string $context ): Collection
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_FIELDS ) ) ) {
            return Collection::make();
        }

        $columns = self::qualified( $table, $this->schema->existingColumns( self::TABLE_FIELDS, self::FIELD_COLUMNS ) );
        $query = $this->db->table( $table )->where( $table . '.context', $context )->where( $table . '.state', JoomlaRow::PUBLISHED )
            ->orderBy( $table . '.ordering' );

        if( $groups = $this->schema->tableIfExists( self::TABLE_FIELDS_GROUPS ) )
        {
            $query->leftJoin( $groups, $groups . '.id', '=', $table . '.group_id' )
                ->where( fn( $query ) => $query->where( $table . '.group_id', 0 )->orWhere( $groups . '.state', JoomlaRow::PUBLISHED ) );
            $columns[] = $groups . '.access as group_access';
        }

        return $query->get( $columns );
    }


    /**
     * Fetches the categories the custom fields are assigned to.
     *
     * Joomla shows fields without assigned categories for all articles.
     *
     * @return array<int|string, list<int>> Category IDs by field ID
     */
    public function fieldCategories(): array
    {
        $map = [];

        if( !( $table = $this->schema->tableIfExists( self::TABLE_FIELDS_CATEGORIES ) ) ) {
            return $map;
        }

        foreach( $this->db->table( $table )->get( ['field_id', 'category_id'] ) as $row ) {
            $map[$row->field_id][] = (int) $row->category_id;
        }

        return $map;
    }


    /**
     * Fetches the values of the custom fields of the articles or categories.
     *
     * The values of all contexts share the item IDs, so only the ones of the fields of the context are fetched.
     *
     * @param array<int|string> $ids IDs of the articles or categories
     * @param string $context Context of the custom fields, JoomlaFields::ARTICLE_CONTEXT or JoomlaFields::CATEGORY_CONTEXT
     * @return array<int|string, array<int|string, list<string>>> Values by article or category ID and field ID
     */
    public function fieldValues( array $ids, string $context ): array
    {
        $values = [];

        if( !( $table = $this->schema->tableIfExists( self::TABLE_FIELDS_VALUES ) ) || !( $fields = $this->schema->tableIfExists( self::TABLE_FIELDS ) ) ) {
            return $values;
        }

        $fieldIds = fn( $query ) => $query->select( 'id' )->from( $fields )->where( 'context', $context );
        $fetch = fn( $chunk ) => $this->db->table( $table )->whereIn( 'item_id', $chunk )->whereIn( 'field_id', $fieldIds )
            ->get( ['field_id', 'item_id', 'value'] );
        // the item IDs of the field values are strings because the column is a varchar, so integers wouldn't use its index
        $rows = Database::chunked( array_map( 'strval', $ids ), $fetch );

        foreach( $rows as $row ) {
            $values[$row->item_id][$row->field_id][] = (string) $row->value;
        }

        return $values;
    }


    /**
     * Fetches the published tags of the articles or categories in the order Joomla shows them.
     *
     * Joomla doesn't sort the tags and MySQL returns them in the order of the tag IDs.
     *
     * @param array<int|string> $ids IDs of the articles or categories
     * @param string $type Type of the tagged items, "com_content.article" or "com_content.category"
     * @return array<int|string, list<\stdClass>> Tags with title, access level and language by article or category ID
     */
    public function tags( array $ids, string $type ): array
    {
        $tags = [];

        // the tags table isn't checked if the map table doesn't exist
        if( !( $map = $this->schema->tableIfExists( self::TABLE_CONTENTITEM_TAG_MAP ) ) || !( $table = $this->schema->tableIfExists( self::TABLE_TAGS ) ) ) {
            return $tags;
        }

        $columns = [$map . '.content_item_id', ...self::qualified( $table, $this->schema->existingColumns( self::TABLE_TAGS, ['title', 'access', 'language'] ) )];
        $fetch = fn( $chunk ) => $this->db->table( $map )
            ->join( $table, $table . '.id', '=', $map . '.tag_id' )
            ->where( $map . '.type_alias', $type )
            ->whereIn( $map . '.content_item_id', $chunk )
            ->where( $table . '.published', JoomlaRow::PUBLISHED )
            ->orderBy( $map . '.tag_id' )
            ->get( $columns );

        $rows = Database::chunked( $ids, $fetch );

        foreach( $rows as $row ) {
            $tags[$row->content_item_id][] = $row;
        }

        return $tags;
    }


    /**
     * Fetches the published modules of the site whose module type is enabled ordered by their position.
     *
     * The content of custom modules is inserted for the shortcodes of the "Content - Load Modules" plugin.
     *
     * @return Collection<int, \stdClass>
     */
    public function modules(): Collection
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_MODULES ) ) ) {
            return Collection::make();
        }

        $query = $this->db->table( $table )
            ->where( $table . '.client_id', JoomlaExtensions::SITE_CLIENT )
            ->where( $table . '.published', JoomlaRow::PUBLISHED )
            ->orderBy( $table . '.position' )
            ->orderBy( $table . '.ordering' );
        // the module types are extensions too
        $this->extensions->whereModuleEnabled( $query, $table );

        $columns = $this->schema->existingColumns( self::TABLE_MODULES, self::MODULE_COLUMNS );
        return $query->get( self::qualified( $table, $columns ) );
    }


    /**
     * Fetches the IDs of the menu items the modules are assigned to.
     *
     * @return array<int|string, list<int>>|null Menu item IDs by module ID, 0 for all and negative IDs for the excluded menu items,
     *  or NULL if the assignments are unknown
     */
    public function moduleMenus(): ?array
    {
        if( !( $map = $this->schema->tableIfExists( self::TABLE_MODULES_MENU ) ) ) {
            return null;
        }

        $menus = [];

        foreach( $this->db->table( $map )->get( ['moduleid', 'menuid'] ) as $row ) {
            $menus[$row->moduleid][] = (int) $row->menuid;
        }

        return $menus;
    }


    /**
     * Fetches the published redirects of the "Redirect" component.
     *
     * @return Collection<int, \stdClass>
     */
    public function redirects(): Collection
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_REDIRECT_LINKS ) ) ) {
            return Collection::make();
        }

        $columns = $this->schema->existingColumns( self::TABLE_REDIRECT_LINKS, self::REDIRECT_COLUMNS );
        return $this->db->table( $table )->where( 'published', JoomlaRow::PUBLISHED )->orderBy( 'id' )->get( $columns );
    }


    /**
     * Returns the published menu items of all menus of the site ordered by their position.
     *
     * All menu items are fetched at once because the menus of a site are small and used several times.
     *
     * @return Collection<int|string, \stdClass> Menu items by ID
     */
    public function menuItems(): Collection
    {
        return $this->menuItems ??= $this->db->table( $this->schema->table( self::TABLE_MENU ) )
            ->where( 'published', JoomlaRow::PUBLISHED )->where( 'client_id', JoomlaExtensions::SITE_CLIENT )
            ->orderBy( 'lft', 'asc' )->get( $this->menuColumns() )->reject( JoomlaRow::isRootRow( ... ) )->keyBy( 'id' );
    }


    /**
     * Fetches the names of the users.
     *
     * @param array<int|string> $ids User IDs
     * @return array<int|string, string> Names by user ID
     */
    public function users( array $ids ): array
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_USERS ) ) ) {
            return [];
        }

        // the user IDs are field values stored as strings but the ID column is an integer, so they are compared as integers
        $fetch = fn( $chunk ) => $this->db->table( $table )->whereIn( 'id', array_map( 'intval', $chunk ) )->get( ['id', 'name'] );
        return Database::chunked( $ids, $fetch )->mapWithKeys( fn( $row ) => [$row->id => (string) $row->name] )->all();
    }


    /**
     * Fetches the titles of the user groups ordered by their position in the tree like Joomla lists them.
     *
     * @return array<int|string, string> Titles by user group ID
     */
    public function userGroups(): array
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_USERGROUPS ) ) ) {
            return [];
        }

        return $this->db->table( $table )->orderBy( 'lft' )->pluck( 'title', 'id' )->map( fn( $title ) => (string) $title )->all();
    }


    /**
     * Returns the Joomla view levels and if guests can see their articles, categories and menu items.
     *
     * Without view levels in the database, only level 1 is public.
     */
    public function levels(): JoomlaLevels
    {
        return $this->levels ??= $this->fetchLevels();
    }


    /**
     * Fetches the Joomla view levels, the user groups and the guest group of the users component.
     */
    protected function fetchLevels(): JoomlaLevels
    {
        if( !( $table = $this->schema->tableIfExists( self::TABLE_VIEWLEVELS ) ) ) {
            return new JoomlaLevels();
        }

        $groups = $this->schema->tableIfExists( self::TABLE_USERGROUPS );
        $parents = $groups ? $this->db->table( $groups )->pluck( 'parent_id', 'id' )->map( fn( $id ) => (int) $id )->all() : null;

        return JoomlaLevels::read( $this->db->table( $table )->get( ['id', 'title', 'rules'] ), $parents, $this->extensions->guestGroup() );
    }


    /**
     * Returns the existing columns of the menu items used by Joomla.
     *
     * Joomla 3 doesn't use the publishing dates of the menu items, so they aren't fetched.
     *
     * @return array<int, string> Existing column names
     */
    protected function menuColumns(): array
    {
        $columns = $this->schema->existingColumns( self::TABLE_MENU, self::MENU_COLUMNS );

        if( !$this->extensions->version()->upTo( JoomlaVersion::JOOMLA_3 ) ) {
            return $columns;
        }

        return array_values( array_diff( $columns, self::SCHEDULE_COLUMNS ) );
    }


    /**
     * Fetches the existing columns of the Joomla articles in chunks.
     *
     * @param array<int|string> $ids IDs of the articles
     * @param array<int, string> $columns Column names, missing ones are skipped
     * @return array<int|string, \stdClass> Articles by ID
     */
    protected function fetchArticles( array $ids, array $columns ): array
    {
        $table = $this->schema->table( self::TABLE_CONTENT );
        $columns = $this->schema->existingColumns( self::TABLE_CONTENT, $columns );
        $fetch = fn( $chunk ) => $this->db->table( $table )->whereIn( 'id', $chunk )->get( $columns );

        return Database::chunked( $ids, $fetch )->keyBy( 'id' )->all();
    }


    /**
     * Returns the column names qualified by the table name.
     *
     * @param string $table Prefixed table name
     * @param array<int, string> $columns Column names
     * @return array<int, string> Column names like "table.column"
     */
    protected static function qualified( string $table, array $columns ): array
    {
        return array_map( fn( $column ) => $table . '.' . $column, $columns );
    }
}
