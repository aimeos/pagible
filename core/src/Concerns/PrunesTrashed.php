<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;


/**
 * Selects the trashed items of all tenants whose retention period is over.
 */
trait PrunesTrashed
{
    /**
     * Default number of days trashed items are kept, see "cms.prune"
     */
    public const PRUNE_DAYS = 30;


    /**
     * Returns the date the items must have been trashed before to be pruned.
     *
     * @return Carbon|null Cutoff date or NULL if pruning is disabled by FALSE as "cms.prune" value
     */
    protected static function cutoff() : ?Carbon
    {
        $days = config( 'cms.prune', self::PRUNE_DAYS );

        if( $days === false || $days === null || $days === '' ) {
            return null;
        }

        return now()->subDays( (int) $days );
    }


    /**
     * Returns the query for the items of all tenants trashed longer than the configured days.
     *
     * @return Builder<static> Query for the expired items
     */
    protected static function expired() : Builder
    {
        $query = static::withoutTenancy();

        // nothing is pruned if pruning is disabled
        if( !$date = static::cutoff() ) {
            return $query->whereRaw( '1 = 0' );
        }

        return $query->where( 'deleted_at', '<=', $date );
    }
}
