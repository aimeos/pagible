<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;


/**
 * Sampling decision for high-volume read streams (search, JSON:API) based on "cms.watch.sample".
 *
 * Audit streams (content changes, auth, contact) do not use this trait and are always complete;
 * only the high-traffic read listeners sample so their volume can be reduced on busy sites.
 */
trait Sampling
{
    /**
     * Tells whether the current entry should be kept according to the "cms.watch.sample" rate.
     *
     * 1.0 (the default) keeps everything; a lower rate randomly drops that fraction of entries.
     */
    protected function sampled() : bool
    {
        $rate = (float) config( 'cms.watch.sample', 1.0 );

        return $rate >= 1.0 || mt_rand() / mt_getrandmax() < $rate;
    }
}
