<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class SaveElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        try {
            $element = Resource::saveElement( $args['id'], $args['input'] ?? [], Auth::user(), $args['files'] ?? null, $args['latestId'] ?? null );
        } catch( \InvalidArgumentException $e ) {
            throw new \GraphQL\Error\Error( $e->getMessage() );
        }

        Resource::broadcast( $element, Auth::user() );

        return $element;
    }
}
