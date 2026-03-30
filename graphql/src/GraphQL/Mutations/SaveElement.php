<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
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
            return Resource::saveElement( $args['id'], $args['input'] ?? [], Utils::editor( Auth::user() ), $args['files'] ?? [] );
        } catch( \InvalidArgumentException $e ) {
            throw new \GraphQL\Error\Error( $e->getMessage() );
        }
    }
}
