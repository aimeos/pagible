<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class AddElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        try {
            return Resource::addElement( $args['input'] ?? [], Auth::user(), $args['files'] ?? [] );
        } catch( \InvalidArgumentException $e ) {
            throw new \GraphQL\Error\Error( $e->getMessage() );
        }
    }
}
