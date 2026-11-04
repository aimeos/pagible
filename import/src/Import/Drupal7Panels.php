<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;


/**
 * Reads the Panelizer displays and the custom content panes of the Panels module of a Drupal 7 database in the format of Drupal 8+.
 */
class Drupal7Panels
{
    /** @var array<string, array<string, mixed>> Configuration in the format of Drupal 8 by name */
    protected array $configs = [];
    /** @var array<int, array<string, mixed>> Layout Builder sections of the default Panels displays by display ID and bundle */
    protected array $sections = [];
    /** @var array<int, bool> IDs of the default Panels displays shared by the nodes */
    protected array $defaults = [];
    /** @var array<int, Collection<int, \stdClass>> Shown panes of the Panels displays of the current nodes by display ID */
    protected array $panes = [];
    /** @var array<string, bool> Warnings which were shown */
    protected array $warned = [];
    /** @var array<int, string> Panelizer view modes used for the node pages in the order of preference */
    protected array $viewModes = ['page_manager', 'full', 'default'];


    /**
     * @param Connection $db Connection to the Drupal database
     * @param Drupal7Db $d7 Tables, variables, locales and records of the Drupal 7 database
     * @param \Closure(string): string $text Converts the text of a Drupal 7 field to the format of Drupal 8
     * @param \Closure $warn Called with the warning message
     */
    public function __construct( protected Connection $db, protected Drupal7Db $d7, protected \Closure $text, protected \Closure $warn )
    {
    }


    /**
     * Adds the Panelizer displays to the configuration.
     *
     * @param array<string, array<string, mixed>> $configs Configuration in the format of Drupal 8 by name
     * @return array<string, array<string, mixed>> Configuration including the displays
     */
    public function configs( array $configs ): array
    {
        $this->configs = $configs;
        $this->displays();

        return $this->configs;
    }


    /**
     * Returns the Panelizer view modes used for the node pages in the order of preference.
     *
     * @return array<int, string>
     */
    public function modes(): array
    {
        return $this->viewModes;
    }

    /**
     * Returns the custom content panes of the Panels module as body field rows of custom block revisions.
     *
     * @param array<int, int> $pids Pane IDs
     * @return Collection<int, \stdClass>
     */
    public function panes( array $pids ): Collection
    {
        if( !$this->d7->exists( 'panels_pane' ) ) {
            return Collection::make();
        }

        return Database::chunked( $pids, fn( $chunk ) => $this->db->table( 'panels_pane' )->whereIn( 'pid', $chunk )->where( 'type', 'custom' )->get() )
            ->map( function( $row ) {
                $config = Database::unserializeArray( $row->configuration );
                $title = trim( (string) ( $config['title'] ?? '' ) );

                return $this->d7->record( [
                    'entity_id' => (int) $row->pid,
                    'revision_id' => (int) $row->pid,
                    'bundle' => 'block-custom',
                    'deleted' => 0,
                    'langcode' => 'und',
                    'delta' => 0,
                    'body_value' => ( $this->text )( ( $title !== '' ? '<h2>' . e( $title ) . '</h2>' : '' ) . (string) ( $config['body'] ?? '' ) ),
                    'body_summary' => '',
                    'body_format' => (string) ( $config['format'] ?? '' ),
                ] );
            } );
    }

    /**
     * Fetches the shown panes of the Panels displays at once and replaces the previously fetched ones.
     *
     * @param array<int, int> $dids Panels display IDs
     */
    public function prefetch( array $dids ): void
    {
        $dids = array_values( array_unique( $dids ) );
        $panes = Database::chunked( $dids, fn( $chunk ) => $this->db->table( 'panels_pane' )->whereIn( 'did', $chunk )->where( 'shown', 1 )
            ->orderBy( 'position' )->orderBy( 'pid' )->get() )->groupBy( fn( $pane ) => (int) $pane->did );

        $this->panes = [];

        foreach( $dids as $did ) {
            $this->panes[$did] = $panes->get( $did ) ?? Collection::make();
        }
    }


    /**
     * Returns the Layout Builder section of the panes of the Panels display.
     *
     * Custom content panes become inline blocks, panes of custom blocks block references and
     * panes of the node fields and content field blocks. Other panes are skipped.
     *
     * @param int $did Panels display ID
     * @param string $bundle Content type of the node
     * @return array<string, mixed> Layout Builder section
     */
    public function section( int $did, string $bundle ): array
    {
        if( isset( $this->sections[$did][$bundle] ) ) {
            return $this->sections[$did][$bundle];
        }

        $panes = $this->panes[$did] ?? $this->db->table( 'panels_pane' )->where( 'did', $did )->where( 'shown', 1 )->orderBy( 'position' )->orderBy( 'pid' )->get();
        $content = (array) ( $this->configs["core.entity_view_display.node.{$bundle}.default"]['content'] ?? [] );
        uasort( $content, fn( $a, $b ) => ( (array) $a )['weight'] <=> ( (array) $b )['weight'] );
        $components = [];

        foreach( $panes as $pane )
        {
            $subtype = (string) $pane->subtype;
            $plugins = match( (string) $pane->type ) {
                'custom' => ['inline_block:block-custom'],
                'block' => preg_match( '/^block-(\d+)$/', $subtype, $m ) ? ['block_content:d7-block-' . $m[1]] : null,
                'entity_field' => preg_match( '/^node:(\w+)$/', $subtype, $m ) ? ["field_block:node:{$bundle}:{$m[1]}"] : null,
                'node_body' => ["field_block:node:{$bundle}:body"],
                'node_content', 'entity_view' => array_map( fn( $field ) => "field_block:node:{$bundle}:{$field}", array_keys( $content ) ),
                default => null,
            };

            if( $plugins === null )
            {
                $type = $pane->type . ':' . $subtype;

                if( !isset( $this->warned['pane:' . $type] ) ) {
                    $this->warned['pane:' . $type] = true;
                    ( $this->warn )( "Skipped panel panes of type \"{$type}\", not supported" );
                }

                continue;
            }

            foreach( $plugins as $idx => $plugin )
            {
                $components[] = [
                    'region' => $this->region( (string) $pane->panel ),
                    'weight' => (int) $pane->position * 1000 + $idx,
                    'configuration' => ['id' => $plugin] + ( $pane->type === 'custom' ? ['block_revision_id' => (int) $pane->pid] : [] ),
                ];
            }
        }

        $section = ['components' => $components];

        // only the sections of the default displays are used by several nodes
        if( isset( $this->defaults[$did] ) ) {
            $this->sections[$did][$bundle] = $section;
        }

        return $section;
    }

    /**
     * Adds the default Panelizer displays of the content types as Layout Builder sections.
     *
     * Content types without default display show their fields like without Panelizer.
     */
    protected function displays(): void
    {
        if( !$this->d7->exists( 'panelizer_entity' ) || !$this->d7->exists( 'panels_pane' ) ) {
            return;
        }

        $defaults = $this->d7->exists( 'panelizer_defaults' )
            ? $this->db->table( 'panelizer_defaults' )->where( 'entity_type', 'node' )->where( 'did', '>', 0 )->whereIn( 'view_mode', $this->viewModes )
                ->where( 'name', 'like', '%:default%' )->get()->sortBy( fn( $row ) => array_search( $row->view_mode, $this->viewModes, true ) )->unique( 'panelizer_type' )
                ->pluck( 'did', 'panelizer_type' )
            : Collection::make();
        $this->defaults = array_fill_keys( $defaults->map( fn( $did ) => (int) $did )->values()->all(), true );
        $types = $this->db->table( 'panelizer_entity as p' )->join( 'node as n', 'n.nid', '=', 'p.entity_id' )
            ->where( 'p.entity_type', 'node' )->where( 'p.did', '>', 0 )->distinct()->pluck( 'n.type' );

        foreach( $types->merge( $defaults->keys() )->map( strval( ... ) )->unique() as $type )
        {
            $key = "core.entity_view_display.node.{$type}.default";
            $content = (array) ( $this->configs[$key]['content'] ?? [] );
            uasort( $content, fn( $a, $b ) => ( (array) $a )['weight'] <=> ( (array) $b )['weight'] );

            $section = ( $did = (int) $defaults->get( $type ) )
                ? $this->section( $did, $type )
                : ['components' => array_map( fn( $field ) => ['region' => 'content', 'configuration' => ['id' => "field_block:node:{$type}:{$field}"]], array_keys( $content ) )];

            $this->configs[$key] = ['content' => $content, 'third_party_settings' => ['layout_builder' => ['enabled' => true, 'allow_custom' => true, 'sections' => [$section]]]];
        }
    }

    /**
     * Returns the Layout Builder region of a Panels region.
     *
     * @param string $panel Name of the Panels region, e.g. "left" or "middle"
     */
    protected function region( string $panel ): string
    {
        return match( true ) {
            (bool) preg_match( '/^(top|header)/', $panel ) => 'top',
            (bool) preg_match( '/^(bottom|footer)/', $panel ) => 'bottom',
            (bool) preg_match( '/^(left|first|sidebar_first)/', $panel ) => 'first',
            (bool) preg_match( '/^(right|second|sidebar_second)/', $panel ) => 'second',
            (bool) preg_match( '/^(third|fourth)/', $panel, $m ) => $m[1],
            default => 'content',
        };
    }
}
