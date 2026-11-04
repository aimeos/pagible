<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Detects the nodes and custom blocks changed since the last import.
 *
 * Nodes are changed too if the menu links, terms, media or custom blocks shown on their pages were changed.
 */
abstract class DrupalTracker
{
    /**
     * Initializes the tracker.
     *
     * @param string $menu Name of the menu used for the page hierarchy
     * @param \Closure(\stdClass): ?int $nodeId Returns the node ID of a menu link
     */
    public function __construct( protected string $menu, protected \Closure $nodeId )
    {
    }


    /**
     * Returns the UUIDs of the custom blocks changed since the time.
     *
     * @param int $since Unix timestamp of the --since option
     * @return array<int, string> Block UUIDs
     */
    abstract public function blocks( int $since ): array;


    /**
     * Returns the IDs of the nodes whose menu links, terms, media or custom blocks changed since the time.
     *
     * @param int $since Unix timestamp of the --since option
     * @param array<int, int> $nids IDs of all imported nodes
     * @param array<string, array{0: string, 1: string, 2: string}> $fields Entity type, field name and "block_content", "media" or "text" by field table
     * @return array<int, int> Node IDs
     */
    abstract public function nodes( int $since, array $nids, array $fields ): array;
}
