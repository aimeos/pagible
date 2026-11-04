<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Detects the changes by the modification dates stored by Drupal 8 and newer.
 */
class Drupal8Tracker extends DrupalTracker
{
    /**
     * Initializes the tracker.
     *
     * @param Drupal8 $reader Reader for the Drupal database
     * @param string $menu Name of the menu used for the page hierarchy
     * @param \Closure(\stdClass): ?int $nodeId Returns the node ID of a menu link
     */
    public function __construct( protected Drupal8 $reader, string $menu, \Closure $nodeId )
    {
        parent::__construct( $menu, $nodeId );
    }


    /**
     * Returns the UUIDs of the custom blocks changed since the time.
     *
     * @param int $since Unix timestamp of the --since option
     * @return array<int, string> Block UUIDs
     */
    public function blocks( int $since ): array
    {
        return $this->reader->changedBlocks( $since );
    }


    /**
     * Returns the IDs of the nodes whose menu links, terms, media or custom blocks changed since the time.
     *
     * Media and custom blocks are found by the reference fields of the nodes and their paragraphs
     * and by their UUIDs embedded in the text fields.
     *
     * @param int $since Unix timestamp of the --since option
     * @param array<int, int> $nids IDs of all imported nodes
     * @param array<string, array{0: string, 1: string, 2: string}> $fields Entity type, field name and "block_content", "media" or "text" by field table
     * @return array<int, int> Node IDs
     */
    public function nodes( int $since, array $nids, array $fields ): array
    {
        // the titles of the menu links are used as page names
        $linked = $this->reader->changedLinks( $this->menu, $since )->map( $this->nodeId )->all();

        return array_values( array_unique( array_map( intval( ... ), array_filter( [...$linked, ...$this->reader->referencing( $since, $fields )] ) ) ) );
    }
}
