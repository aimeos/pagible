<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Database\Eloquent\Builder;


/**
 * Selects the trashed items of all tenants whose retention period is over.
 */
trait PrunesTrashed
{
    /**
     * Returns the query for the items of all tenants trashed longer than the configured days.
     *
     * @return Builder<static> Query for the expired items
     */
    protected static function expired() : Builder
    {
        return static::withoutTenancy()->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) );
    }
}
