<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;


/**
 * Forwards the calls of a facade to the static methods of a resource class.
 *
 * Not final because the facades replace it by a mock in tests.
 *
 * @internal
 */
class Proxy
{
    /**
     * @param class-string $class Resource class with static methods
     */
    public function __construct( private string $class )
    {
    }


    /**
     * @param array<int|string, mixed> $args
     */
    public function __call( string $method, array $args ) : mixed
    {
        return $this->class::$method( ...$args );
    }
}
