<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;


/**
 * Reads the custom blocks and the blocks of the Bean module of a Drupal 7 database in the format of Drupal 8+.
 */
class Drupal7Blocks
{
    /** @var int|null Offset added to the IDs of the blocks of the Bean module to avoid collisions with custom blocks */
    protected ?int $offset = null;


    /**
     * @param Connection $db Connection to the Drupal database
     * @param Drupal7Db $d7 Tables, variables, locales and records of the Drupal 7 database
     * @param \Closure(string): string $text Converts the text of a Drupal 7 field to the format of Drupal 8
     */
    public function __construct( protected Connection $db, protected Drupal7Db $d7, protected \Closure $text )
    {
    }


    /**
     * Adds the configuration of the custom block body field.
     *
     * @param array<string, array<string, mixed>> $configs Configuration in the format of Drupal 8 by name
     * @return array<string, array<string, mixed>> Configuration including the custom block body field
     */
    public function configs( array $configs ): array
    {
        $configs['field.storage.block_content.body'] = ['type' => 'text_with_summary', 'settings' => []];
        $configs['field.field.block_content.block-custom.body'] = ['label' => 'Body', 'settings' => []];
        $configs['core.entity_view_display.block_content.block-custom.default'] = ['content' => ['body' => ['weight' => 0, 'label' => 'hidden']]];

        return $configs;
    }


    /**
     * Returns the body field rows of the custom blocks.
     *
     * @param array<int, int> $ids Block IDs
     * @return Collection<int, \stdClass>
     */
    public function bodies( array $ids ): Collection
    {
        if( !$this->d7->exists( 'block_custom' ) ) {
            return Collection::make();
        }

        $rows = $this->db->table( 'block_custom' )->whereIn( 'bid', $ids )->get();
        // block bodies localized by the i18n_block module
        $locales = $this->d7->locales( 'blocks', $rows->map( fn( $row ) => 'block:' . $row->bid . ':body' )->values()->all() );
        $list = [];

        foreach( $rows as $row )
        {
            $bodies = [$this->d7->langcode() => (string) $row->body] + ( $locales['block:' . $row->bid . ':body'] ?? [] );

            foreach( $bodies as $langcode => $body )
            {
                $list[] = $this->d7->record( [
                    'entity_id' => (int) $row->bid,
                    'revision_id' => (int) $row->bid,
                    'bundle' => 'block-custom',
                    'deleted' => 0,
                    'langcode' => (string) $langcode,
                    'delta' => 0,
                    'body_value' => ( $this->text )( $body ),
                    'body_summary' => '',
                    'body_format' => (string) $row->format,
                ] );
            }
        }

        return Collection::make( $list );
    }

    /**
     * Returns the offset added to the IDs of the blocks of the Bean module, i.e. the highest custom block ID.
     */
    public function offset(): int
    {
        return $this->offset ??= $this->d7->exists( 'block_custom' ) ? (int) $this->db->table( 'block_custom' )->max( 'bid' ) : 0;
    }

    /**
     * Returns the custom blocks and the blocks of the Bean module in the format of the block_content and block_content_field_data tables.
     *
     * The IDs of the beans are added to the highest custom block ID and their types are the block types.
     *
     * @param string $column "id" or "uuid"
     * @param array<int, int|string> $values Block IDs or UUIDs, empty for all blocks
     * @return Collection<int, \stdClass>
     */
    public function rows( string $column, array $values ): Collection
    {
        $list = Collection::make();

        if( $this->d7->exists( 'block_custom' ) )
        {
            $ids = $column === 'uuid'
                ? array_map( fn( $uuid ) => (int) substr( (string) $uuid, 9 ), array_filter( $values, fn( $uuid ) => str_starts_with( (string) $uuid, 'd7-block-' ) ) )
                : array_map( intval( ... ), $values );

            $list = $this->db->table( 'block_custom' )->when( $values, fn( $query ) => $query->whereIn( 'bid', $ids ) )->orderBy( 'bid' )->get()
                ->map( fn( $row ) => $this->d7->record( [
                    'id' => (int) $row->bid,
                    'uuid' => 'd7-block-' . $row->bid,
                    'type' => 'block-custom',
                    'langcode' => $this->d7->langcode(),
                    'info' => (string) $row->info,
                    'status' => 1,
                    'default_langcode' => 1,
                ] ) );
        }

        if( !$this->d7->exists( 'bean' ) ) {
            return $list;
        }

        $offset = $this->offset();
        $query = $this->db->table( 'bean' )->orderBy( 'bid' );

        if( $values && $column === 'uuid' ) {
            $query->whereIn( 'delta', array_map( fn( $uuid ) => substr( (string) $uuid, 8 ), array_filter( $values, fn( $uuid ) => str_starts_with( (string) $uuid, 'd7-bean-' ) ) ) );
        } elseif( $values ) {
            $query->whereIn( 'bid', array_map( fn( $id ) => (int) $id - $offset, $values ) );
        }

        return $list->merge( $query->get()->map( fn( $row ) => $this->d7->record( [
            'id' => (int) $row->bid + $offset,
            'uuid' => 'd7-bean-' . $row->delta,
            'type' => (string) $row->type,
            'langcode' => $this->d7->langcode(),
            'info' => (string) $row->label,
            'status' => 1,
            'default_langcode' => 1,
            'changed' => (int) ( $row->changed ?? 0 ),
        ] ) ) )->values();
    }
}
