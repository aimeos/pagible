<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Carbon;


/**
 * Stores the model timestamps in seconds precision.
 */
trait SecondTimestamps
{
    /**
     * Get the current timestamp in seconds precision.
     *
     * @return Carbon Current timestamp
     */
    public function freshTimestamp()
    {
        return Date::now()->startOfSecond(); // SQL Server workaround
    }
}
