<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;


/**
 * Reads the Joomla version, the options of the components and the enabled plugins and site languages from the extensions table.
 */
class JoomlaExtensions
{
    use JoomlaRemembers;

    /** Name of the articles component */
    public const COMPONENT_CONTENT = 'com_content';
    /** Name of the categories component */
    public const COMPONENT_CATEGORIES = 'com_categories';
    /** Name of the languages component */
    public const COMPONENT_LANGUAGES = 'com_languages';
    /** Name of the menus component */
    public const COMPONENT_MENUS = 'com_menus';
    /** Name of the redirect component */
    public const COMPONENT_REDIRECT = 'com_redirect';
    /** Name of the tags component */
    public const COMPONENT_TAGS = 'com_tags';
    /** Name of the users component */
    public const COMPONENT_USERS = 'com_users';

    /** Joomla client ID of the site, the administrator has 1 */
    public const SITE_CLIENT = 0;

    /** Option of the users component containing the user group of the guests */
    protected const GUEST_GROUP_OPTION = 'guest_usergroup';

    /** Joomla value of enabled extensions */
    protected const ENABLED = 1;

    /** Name of the Joomla extensions table without prefix */
    protected const TABLE = 'extensions';
    /** Column of the extensions and the rows of the extensions containing the Joomla client ID */
    protected const CLIENT_COLUMN = 'client_id';

    /** Extension types of the components, languages, modules, plugins and the Joomla files */
    protected const TYPE_COMPONENT = 'component';
    protected const TYPE_LANGUAGE = 'language';
    protected const TYPE_MODULE = 'module';
    protected const TYPE_PLUGIN = 'plugin';
    protected const TYPE_FILE = 'file';

    /** Name of the file extension of the Joomla core files containing the Joomla version */
    protected const JOOMLA_FILES = 'joomla';

    /** Joomla extension states of the installed plugins, discovered ones have -1 */
    protected const INSTALLED_STATES = [0, 1];

    /** @var array<string, mixed> Fetched values by cache key, NULL for the ones which don't exist */
    protected array $cache = [];
    /** Major version of Joomla fetched once by version() */
    protected ?JoomlaVersion $version = null;


    /**
     * @param Connection $db Connection to the Joomla database
     * @param JoomlaSchema $schema Existing Joomla tables and columns
     */
    public function __construct( protected Connection $db, protected JoomlaSchema $schema )
    {
    }


    /**
     * Returns the options of the component, e.g. the ones of the articles component the articles, categories and menu items can override.
     *
     * @param string $component Name of the Joomla component
     * @return array<string, mixed> Options by name
     */
    public function componentParams( string $component ): array
    {
        return $this->cached( 'component/' . $component, fn() => JoomlaValue::decode( $this->fetchComponentParams( $component ) ) ) ?? [];
    }


    /**
     * Returns the user group of the guests configured in the users component.
     *
     * @return int|null ID of the user group or NULL if it isn't configured
     */
    public function guestGroup(): ?int
    {
        $guest = $this->componentParams( self::COMPONENT_USERS )[self::GUEST_GROUP_OPTION] ?? null;
        return is_numeric( $guest ) ? (int) $guest : null;
    }


    /**
     * Restricts the query to the modules whose module type is enabled for the same client, all modules match if the installed extensions are unknown.
     *
     * Only module extensions are compared because other extension types like components or plugins can have the same name.
     * The client of the extensions is only compared if the extensions table contains it.
     *
     * @param Builder $query Query of the modules
     * @param string $table Modules table with prefix
     */
    public function whereModuleEnabled( Builder $query, string $table ): void
    {
        if( !$this->known() ) {
            return;
        }

        $ext = $this->schema->table( self::TABLE );
        $client = $this->schema->hasColumn( self::TABLE, self::CLIENT_COLUMN );

        $query->whereExists( fn( $sub ) => $sub->from( $ext )
            ->where( $ext . '.type', self::TYPE_MODULE )
            ->whereColumn( $ext . '.element', $table . '.module' )
            ->when( $client, fn( $sub ) => $sub->whereColumn( $ext . '.' . self::CLIENT_COLUMN, $table . '.' . self::CLIENT_COLUMN ) )
            ->where( $ext . '.enabled', self::ENABLED ) );
    }


    /**
     * Tests if the extensions table exists, otherwise the installed extensions are unknown.
     */
    public function known(): bool
    {
        return $this->schema->tableIfExists( self::TABLE ) !== null;
    }


    /**
     * Returns the access level and the options of the Joomla plugin if it's installed and enabled.
     *
     * Like Joomla, plugins which aren't installed aren't loaded, their access level is checked by JoomlaPlugins.
     *
     * @param string $folder Plugin group, e.g. "system"
     * @param string $element Plugin name, e.g. "languagefilter"
     * @return JoomlaPlugin|null Access level and options or NULL if the plugin isn't enabled
     */
    public function plugin( string $folder, string $element ): ?JoomlaPlugin
    {
        return $this->cached( 'plugin/' . $folder . '/' . $element, fn() => $this->fetchPlugin( $folder, $element ) );
    }


    /**
     * Returns the Joomla language tags of the enabled site languages.
     *
     * Joomla doesn't show the content languages whose site language isn't installed or enabled.
     *
     * @return array<int, string>|null Language tags like "en-GB" or NULL if the installed languages are unknown
     */
    public function siteLanguages(): ?array
    {
        return $this->cached( 'languages', fn() => $this->fetchSiteLanguages() );
    }


    /**
     * Returns the major version of Joomla.
     *
     * @return JoomlaVersion Major version like 3, 4 or 5, unknown if the installed extensions are unknown
     */
    public function version(): JoomlaVersion
    {
        return $this->version ??= new JoomlaVersion( $this->known() ? $this->fetchVersion() : null );
    }


    /**
     * Returns the value fetched once for the cache key if the installed extensions are known.
     *
     * @template T
     * @param string $key Cache key
     * @param \Closure(): T $fetch Fetches the value
     * @return T|null Fetched value or NULL if the installed extensions are unknown
     */
    protected function cached( string $key, \Closure $fetch ): mixed
    {
        return $this->remember( $this->cache, $key, fn() => $this->known() ? $fetch() : null );
    }


    /**
     * Fetches the Joomla language tags of the enabled site languages.
     *
     * @return array<int, string>|null Language tags like "en-GB" or NULL if the installed languages are unknown
     */
    protected function fetchSiteLanguages(): ?array
    {
        if( !$this->schema->hasColumn( self::TABLE, self::CLIENT_COLUMN ) ) {
            return null;
        }

        $rows = $this->query()
            ->where( 'type', self::TYPE_LANGUAGE )
            ->where( self::CLIENT_COLUMN, self::SITE_CLIENT )
            ->get( ['element', 'enabled'] );

        // without language extensions, the installed languages are unknown
        if( $rows->isEmpty() ) {
            return null;
        }

        return $rows->filter( fn( $row ) => (int) $row->enabled === self::ENABLED )
            ->map( fn( $row ) => (string) $row->element )
            ->values()->all();
    }


    /**
     * Fetches the encoded options of the component.
     *
     * @param string $component Name of the Joomla component
     * @return mixed JSON encoded options or NULL if the component isn't installed
     */
    protected function fetchComponentParams( string $component ): mixed
    {
        return $this->query()->where( 'type', self::TYPE_COMPONENT )->where( 'element', $component )->value( 'params' );
    }


    /**
     * Fetches the access level and the options of the plugin if it's installed and enabled.
     *
     * @param string $folder Plugin group, e.g. "system"
     * @param string $element Plugin name, e.g. "languagefilter"
     * @return JoomlaPlugin|null Access level and options or NULL if the plugin isn't enabled
     */
    protected function fetchPlugin( string $folder, string $element ): ?JoomlaPlugin
    {
        $query = $this->query()
            ->where( 'type', self::TYPE_PLUGIN )
            ->where( 'folder', $folder )
            ->where( 'element', $element )
            ->where( 'enabled', self::ENABLED );
        $columns = $this->schema->existingColumns( self::TABLE, ['access', 'state'] );

        if( in_array( 'state', $columns, true ) ) {
            $query->whereIn( 'state', self::INSTALLED_STATES );
        }

        if( !( $row = $query->first( array_merge( ['params'], $columns ) ) ) ) {
            return null;
        }

        // plugins without access level are public like in Joomla versions without the column
        $access = isset( $row->access ) ? (int) $row->access : JoomlaLevels::PUBLIC_LEVEL;

        return new JoomlaPlugin( $access, JoomlaRow::params( $row ) );
    }


    /**
     * Reads the major version of Joomla from the manifest of the Joomla files.
     *
     * @return int|null Major version like 3, 4 or 5 or NULL if it's unknown
     */
    protected function fetchVersion(): ?int
    {
        if( !$this->schema->hasColumn( self::TABLE, 'manifest_cache' ) ) {
            return null;
        }

        $manifest = $this->query()
            ->where( 'type', self::TYPE_FILE )
            ->where( 'element', self::JOOMLA_FILES )
            ->value( 'manifest_cache' );
        $version = JoomlaValue::decode( $manifest )['version'] ?? null;

        if( !is_string( $version ) || !preg_match( '/^([0-9]+)\./', $version, $match ) ) {
            return null;
        }

        return (int) $match[1];
    }


    /**
     * Returns a new query for the Joomla extensions table.
     */
    protected function query(): Builder
    {
        return $this->db->table( $this->schema->table( self::TABLE ) );
    }
}
