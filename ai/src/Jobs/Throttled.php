<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;


/**
 * Thrown if the AI provider calls of the tenant exceed the rate limit.
 */
final class Throttled extends \RuntimeException
{
    /**
     * @param int $delay Seconds until the rate limit allows new calls
     */
    public function __construct( public readonly int $delay )
    {
        parent::__construct( 'Too many translations' );
    }
}
