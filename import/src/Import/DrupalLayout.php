<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Reads the Layout Builder sections of the Drupal nodes.
 */
class DrupalLayout
{
    /** @var array<int, string> Order of the regions of Drupal's core layouts */
    protected array $regions = ['top', 'content', 'first', 'second', 'third', 'fourth', 'bottom'];


    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration and field values of the Drupal database
     */
    public function __construct( protected DrupalData $data )
    {
    }


    /**
     * Returns the revision IDs of the inline blocks and the UUIDs of the reusable blocks of the components.
     *
     * @param array<int, array<string, mixed>> $components Layout Builder components
     * @return array{0: array<int, int>, 1: array<int, string>} Revision IDs of the inline blocks and UUIDs of the reusable blocks
     */
    public function blocks( array $components ): array
    {
        $revisions = $uuids = [];

        foreach( $components as $component )
        {
            $config = (array) ( $component['configuration'] ?? [] );
            $plugin = explode( ':', (string) ( $config['id'] ?? '' ) );

            match( $plugin[0] ) {
                'inline_block' => $revisions[] = (int) ( $config['block_revision_id'] ?? 0 ),
                'block_content' => $uuids[] = $plugin[1] ?? '',
                default => null,
            };
        }

        return [$revisions, $uuids];
    }


    /**
     * Returns the components of the Layout Builder sections in the order of their regions and weights.
     *
     * @param array<int, array<string, mixed>> $sections Layout Builder sections
     * @return array<int, array<string, mixed>> List of components
     */
    public function components( array $sections ): array
    {
        $list = [];

        foreach( $sections as $section )
        {
            $components = array_values( array_map( fn( $component ) => $this->toArray( $component ), (array) ( $section['components'] ?? [] ) ) );

            usort( $components, fn( $a, $b ) => [$this->regionOrder( (string) ( $a['region'] ?? '' ) ), (int) ( $a['weight'] ?? 0 )]
                <=> [$this->regionOrder( (string) ( $b['region'] ?? '' ) ), (int) ( $b['weight'] ?? 0 )] );

            array_push( $list, ...$components );
        }

        return $list;
    }


    /**
     * Returns the Layout Builder sections of the node or NULL if Layout Builder isn't used.
     *
     * Overrides of the node are used if available, otherwise the default layout of the content type.
     *
     * @param object{nid: int, type: string} $node
     * @param string $langcode Drupal language code
     * @param int|null $vid Revision ID of the draft or NULL for the current revision
     * @return array<int, array<string, mixed>>|null List of sections
     */
    public function sections( object $node, string $langcode, ?int $vid = null ): ?array
    {
        $display = $this->data->config( "core.entity_view_display.node.{$node->type}.default" );
        $settings = (array) ( $display['third_party_settings']['layout_builder'] ?? [] );

        if( empty( $settings['enabled'] ) ) {
            return null;
        }

        $sections = [];

        if( !empty( $settings['allow_custom'] ) )
        {
            $rows = $this->data->fieldValues( 'node', 'layout_builder__layout', $vid ?? $node->nid, $langcode, fallback: true, revision: $vid !== null );
            $this->prefetch( $vid !== null );

            foreach( $rows as $row )
            {
                $section = Database::unserialize( $row->layout_builder__layout_section ?? null );
                $sections[] = $this->toArray( $section );
            }
        }

        return $sections ?: array_map( fn( $section ) => $this->toArray( $section ), (array) ( $settings['sections'] ?? [] ) );
    }


    /**
     * Fetches the blocks of the custom layouts fetched together with the last one at once.
     *
     * The layouts of the nodes imported next are fetched together, so their inline and reusable
     * blocks are fetched at once too instead of one query per node and block field table.
     *
     * @param bool $revision TRUE for the layouts of the drafts
     */
    protected function prefetch( bool $revision ): void
    {
        $revisions = $uuids = [];

        foreach( $this->data->fetchedRows( $this->data->fieldTable( 'node', 'layout_builder__layout', $revision ) ) as $rows )
        {
            foreach( $rows as $row )
            {
                $section = $this->toArray( Database::unserialize( $row->layout_builder__layout_section ?? null ) );
                [$ids, $list] = $this->blocks( $this->components( [$section] ) );

                array_push( $revisions, ...$ids );
                array_push( $uuids, ...$list );
            }
        }

        $this->data->prefetchRevisions( 'block_content', $revisions );
        $this->data->prefetchBlocks( $uuids );
    }


    /**
     * Returns the position of the Layout Builder region, unknown regions are sorted last.
     */
    protected function regionOrder( string $region ): int
    {
        $pos = array_search( $region, $this->regions, true );
        return $pos !== false ? (int) $pos : count( $this->regions );
    }


    /**
     * Converts an unserialized Drupal object or configuration array into a nested array.
     *
     * Objects of unavailable classes like Section and SectionComponent are converted
     * by removing the visibility prefixes of their property names.
     *
     * @return array<string, mixed>
     */
    protected function toArray( mixed $value ): array
    {
        $result = [];

        foreach( (array) $value as $key => $item )
        {
            $name = (string) preg_replace( '/^\0[^\0]*\0/', '', (string) $key );

            if( $name === '__PHP_Incomplete_Class_Name' ) {
                continue;
            }

            $result[$name] = is_object( $item ) ? $this->toArray( $item ) : $item;
        }

        // Section objects use camelCase properties, the configuration snake case keys
        return $result;
    }
}
