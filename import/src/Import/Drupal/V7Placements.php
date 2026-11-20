<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Drupal;

use Aimeos\Cms\Import\Database;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;


/**
 * Reads the block placements of the block table and the Context module of a Drupal 7 database in the format of Drupal 8+.
 */
class V7Placements
{
    /** @var array<string, array<string, mixed>> Configuration in the format of Drupal 8 by name */
    protected array $configs = [];


    /**
     * @param Connection $db Connection to the Drupal database
     * @param V7Db $d7 Tables, variables, locales and records of the Drupal 7 database
     * @param \Closure $warn Called with the warning message
     */
    public function __construct( protected Connection $db, protected V7Db $d7, protected \Closure $warn )
    {
    }


    /**
     * Adds the block placements to the configuration.
     *
     * @param array<string, array<string, mixed>> $configs Configuration in the format of Drupal 8 by name
     * @return array<string, array<string, mixed>> Configuration including the block placements
     */
    public function configs( array $configs ): array
    {
        $this->configs = $configs;
        $this->placements();

        return $this->configs;
    }


    /**
     * Returns the visibility condition which matches the listed values.
     *
     * @param string $id Condition plugin ID, e.g. "node_type"
     * @param string $key Name of the setting containing the values, e.g. "bundles"
     * @param array<int, string> $list Values, e.g. content types
     * @return array<string, mixed> Visibility condition in the format of Drupal 8
     */
    protected function condition( string $id, string $key, array $list ): array
    {
        return ['id' => $id, $key => array_combine( $list, $list )];
    }

    /**
     * Returns the values of the block visibility table by block key, e.g. "block:1".
     *
     * @return array<string, array<int, string>>
     */
    protected function conditions( string $table, string $column ): array
    {
        if( !$this->d7->exists( $table ) ) {
            return [];
        }

        return $this->db->table( $table )->get()->groupBy( fn( $row ) => $row->module . ':' . $row->delta )
            ->map( fn( $rows ) => $rows->pluck( $column )->map( strval( ... ) )->unique()->values()->all() )->all();
    }

    /**
     * Adds the block placements of the enabled contexts of the Context module.
     *
     * Only contexts stored in the database are available, contexts only defined in code of
     * features modules are not. Contexts whose conditions can't be converted are skipped.
     *
     * @param string $theme Name of the default theme
     * @param array<int|string, string> $names Role names by role ID
     */
    protected function contexts( string $theme, array $names ): void
    {
        if( !$this->d7->exists( 'context' ) ) {
            return;
        }

        // exported contexts which are disabled are TRUE
        $disabled = (array) ( $this->d7->variable( 'context_status' ) ?? [] );
        $rids = array_flip( array_map( strval( ... ), $names ) );

        foreach( $this->db->table( 'context' )->orderBy( 'name' )->get() as $row )
        {
            $name = (string) $row->name;
            $blocks = (array) ( Database::unserializeArray( $row->reactions )['block']['blocks'] ?? [] );

            if( !empty( $disabled[$name] ) || !$blocks ) {
                continue;
            }

            if( ( $visibility = $this->contextVisibility( Database::unserializeArray( $row->conditions ), (int) ( $row->condition_mode ?? 0 ), $rids ) ) === null )
            {
                ( $this->warn )( "  Skipped blocks of context \"{$name}\" because its conditions are not supported" );
                continue;
            }

            foreach( $blocks as $block )
            {
                $block = (array) $block;
                $module = (string) ( $block['module'] ?? '' );
                $delta = (string) ( $block['delta'] ?? '' );

                $this->configs['block.block.context_' . Str::slug( $name . '_' . $module . '_' . $delta, '_' )] = [
                    'theme' => $theme,
                    'status' => true,
                    'region' => (string) ( $block['region'] ?? '' ),
                    'weight' => (int) ( $block['weight'] ?? 0 ),
                    'plugin' => V7Blocks::plugin( $module, $delta ),
                    'visibility' => $visibility,
                ];
            }
        }
    }

    /**
     * Returns the block visibility of the context conditions in the format of Drupal 8.
     *
     * Drupal 8 shows blocks if all conditions match, so contexts which are active if any
     * of several conditions match are not supported except the sitewide condition.
     *
     * @param array<int|string, mixed> $conditions Context conditions with their values
     * @param int $mode 1 if all conditions must match, 0 if any condition must match
     * @param array<string, int|string> $rids Role IDs by role name
     * @return array<string, array<string, mixed>>|null Visibility conditions or NULL if the context isn't supported
     */
    protected function contextVisibility( array $conditions, int $mode, array $rids ): ?array
    {
        $visibility = [];
        $conditions = array_filter( array_map( fn( $cond ) => array_map( strval( ... ), array_keys( array_filter( (array) ( ( (array) $cond )['values'] ?? [] ) ) ) ), $conditions ) );
        $sitewide = isset( $conditions['sitewide'] );
        unset( $conditions['sitewide'] );

        if( $mode === 0 && ( $sitewide || count( $conditions ) > 1 ) ) {
            return $sitewide ? [] : null;
        }

        if( !$sitewide && !$conditions ) {
            return null;
        }

        foreach( $conditions as $type => $list )
        {
            switch( $type )
            {
                case 'path':
                    $negate = array_filter( $list, fn( $path ) => str_starts_with( $path, '~' ) );

                    if( $negate && count( $negate ) !== count( $list ) ) {
                        return null;
                    }

                    $visibility['request_path'] = $this->requestPath( array_map( fn( $path ) => ltrim( trim( $path ), '~' ), $list ), (bool) $negate );
                    break;
                case 'node':
                    $visibility['node_type'] = $this->condition( 'node_type', 'bundles', $list );
                    break;
                case 'user':
                    $visibility['user_role'] = $this->condition( 'user_role', 'roles', array_map( fn( $name ) => $this->d7->role( (int) ( $rids[$name] ?? 0 ), $name ), $list ) );
                    break;
                case 'language':
                    $visibility['language'] = $this->condition( 'language', 'langcodes', $list );
                    break;
                default:
                    return null;
            }
        }

        return $visibility;
    }

    /**
     * Adds the block placements in the default theme.
     */
    protected function placements(): void
    {
        $theme = (string) ( $this->d7->variable( 'theme_default' ) ?? 'bartik' );
        $names = $this->d7->exists( 'role' ) ? $this->db->table( 'role' )->pluck( 'name', 'rid' )->all() : [];

        $this->contexts( $theme, $names );

        if( !$this->d7->exists( 'block' ) ) {
            return;
        }

        $rows = $this->db->table( 'block' )->where( 'theme', $theme )->where( 'status', 1 )->where( 'region', '!=', '-1' )->get();
        $types = $this->conditions( 'block_node_type', 'type' );
        $roles = $this->conditions( 'block_role', 'rid' );
        $langs = $this->conditions( 'block_language', 'language' );

        foreach( $rows as $row )
        {
            $key = $row->module . ':' . $row->delta;
            $pages = trim( (string) $row->pages );
            $visibility = [];

            // blocks only shown on the listed pages without pages are never shown
            if( (int) $row->visibility === 1 && $pages === '' ) {
                continue;
            }

            if( (int) $row->visibility === 2 ) {
                $visibility['php'] = ['id' => 'php'];
            } elseif( $pages !== '' ) {
                $visibility['request_path'] = $this->requestPath( array_map( trim( ... ), preg_split( '/\R/', $pages ) ?: [] ), (int) $row->visibility === 0 );
            }

            if( $list = $types[$key] ?? [] ) {
                $visibility['node_type'] = $this->condition( 'node_type', 'bundles', $list );
            }

            if( $list = $roles[$key] ?? [] ) {
                $visibility['user_role'] = $this->condition( 'user_role', 'roles', array_map( fn( $rid ) => $this->d7->role( (int) $rid, (string) ( $names[$rid] ?? $rid ) ), $list ) );
            }

            if( $list = $langs[$key] ?? [] ) {
                $visibility['language'] = $this->condition( 'language', 'langcodes', $list );
            }

            $this->configs['block.block.' . Str::slug( $row->module . '_' . $row->delta, '_' )] = [
                'theme' => $theme,
                'status' => true,
                'region' => (string) $row->region,
                'weight' => (int) $row->weight,
                'plugin' => V7Blocks::plugin( (string) $row->module, (string) $row->delta ),
                'visibility' => $visibility,
            ];
        }
    }

    /**
     * Returns the request path visibility condition of the Drupal 7 paths.
     *
     * @param array<int, string> $paths Trimmed Drupal 7 paths without leading slash, e.g. "node/1" or "<front>"
     * @param bool $negate TRUE if the blocks are shown on all pages except the listed ones
     * @return array<string, mixed> Visibility condition in the format of Drupal 8
     */
    protected function requestPath( array $paths, bool $negate ): array
    {
        $paths = array_map( fn( $path ) => $path === '<front>' ? $path : '/' . ltrim( $path, '/' ), $paths );

        return ['id' => 'request_path', 'pages' => implode( "\n", $paths ), 'negate' => $negate];
    }
}
