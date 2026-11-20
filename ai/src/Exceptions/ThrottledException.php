<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Exceptions;

use Aimeos\Cms\Exception;


/**
 * Thrown if the translation provider calls of the tenant exceed the rate limit.
 */
final class ThrottledException extends Exception
{
    /**
     * @param int $delay Seconds until new calls are allowed
     */
    public function __construct( public readonly int $delay )
    {
        parent::__construct( __( 'Too many translations, please try again later' ) );
    }
}
