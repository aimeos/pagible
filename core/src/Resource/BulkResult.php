<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;


/**
 * Result of saving the same input to several items.
 */
final readonly class BulkResult
{
    /**
     * @param list<string> $ids IDs of the saved items in processing order
     * @param array<string, string> $latest New latest version ID by item ID
     * @param array<string, mixed> $data Field values applied to every saved item
     * @param int $failed Number of attempted items which couldn't be saved
     */
    public function __construct(
        public array $ids = [],
        public array $latest = [],
        public array $data = [],
        public int $failed = 0,
    ) {
    }
}
