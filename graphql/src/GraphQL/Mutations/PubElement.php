<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Auth;


final class PubElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        try {
            Validation::publishAt( $args['at'] ?? null );
        } catch( \InvalidArgumentException $e ) {
            throw new \GraphQL\Error\Error( $e->getMessage() );
        }

        return Resource::publish( Element::class, $args['id'], Utils::editor( Auth::user() ), $args['at'] ?? null, [
            'latest.files' => fn( $q ) => $q->select( 'cms_files.id' )
        ] )->all();
    }
}
