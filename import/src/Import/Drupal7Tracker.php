<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Treats all custom blocks as changed and no nodes in addition to the ones with a newer changed date.
 *
 * Drupal 7 doesn't store when menu links, terms or blocks were changed, so changed menu links
 * and terms aren't detected and the custom blocks are always imported again.
 */
class Drupal7Tracker extends DrupalTracker
{
    /**
     * Initializes the tracker.
     *
     * @param Drupal7 $reader Reader for the Drupal database
     * @param string $menu Name of the menu used for the page hierarchy
     * @param \Closure(\stdClass): ?int $nodeId Returns the node ID of a menu link
     */
    public function __construct( protected Drupal7 $reader, string $menu, \Closure $nodeId )
    {
        parent::__construct( $menu, $nodeId );
    }


    /**
     * Returns the UUIDs of all custom blocks.
     *
     * @param int $since Unix timestamp of the --since option, unused because Drupal 7 doesn't store when blocks were changed
     * @return array<int, string> Block UUIDs
     */
    public function blocks( int $since ): array
    {
        return $this->reader->blockInfos()->pluck( 'uuid' )->map( strval( ... ) )->unique()->values()->all();
    }


    /**
     * Returns no node IDs because Drupal 7 doesn't store when menu links or terms were changed.
     *
     * @param int $since Unix timestamp of the --since option, unused
     * @param array<int, int> $nids IDs of all imported nodes, unused
     * @param array<string, array{0: string, 1: string, 2: string}> $fields Unused because Drupal 7 stores media in the nodes
     * @return array<int, int> Empty list
     */
    public function nodes( int $since, array $nids, array $fields ): array
    {
        return [];
    }
}
