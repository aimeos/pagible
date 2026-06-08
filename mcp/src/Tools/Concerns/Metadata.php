<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools\Concerns;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


/**
 * Shared result mapping for the search tools.
 */
trait Metadata
{
    /**
     * Wraps the type-specific fields with the common id and version/timestamp envelope.
     *
     * @param Element|File|Page $item Model with its "latest" version relation loaded
     * @param array<string, mixed> $fields Type-specific fields taken from the version data
     * @return array<string, mixed> Mapped result row
     */
    protected function result( Element|File|Page $item, array $fields ) : array
    {
        $latest = $item->latest;

        return ['id' => $item->id] + $fields + [
            'lang' => $latest?->lang,
            'editor' => $latest?->editor,
            'deleted' => $item->trashed(),
            'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => $item->updated_at?->format( 'Y-m-d H:i:s' ),
        ];
    }
}
