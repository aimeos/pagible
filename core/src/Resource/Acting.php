<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;

use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Calls the methods of a resource for the given user.
 *
 * Returned by Pages::as( $user ) and the other facades, e.g. Pages::as( $user )->publish( $id ).
 * The user is passed as named "user" argument, so optional arguments after it must be named too,
 * e.g. Pages::as( $user )->add( $input, parent: $rootId ).
 *
 * @template T of object
 * @mixin T
 */
final class Acting
{
    /**
     * @param T $resource Resource instance
     * @param Authenticatable|null $user User the methods are called for, NULL for anonymous calls
     */
    public function __construct( private object $resource, private ?Authenticatable $user )
    {
    }


    /**
     * Calls the resource method with the user.
     *
     * @param string $method Name of the public resource method
     * @param array<int|string, mixed> $args Arguments without the user
     * @return mixed Result of the resource method
     */
    public function __call( string $method, array $args ) : mixed
    {
        return $this->resource->{$method}( ...$args, user: $this->user );
    }
}
