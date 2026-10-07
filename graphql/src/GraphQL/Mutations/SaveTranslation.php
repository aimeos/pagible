<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class SaveTranslation
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        $hashes = is_array( $args['hashes'] ) || is_object( $args['hashes'] ) ? (array) $args['hashes'] : [];

        return Resource::saveTranslation( $args['id'], $args['lang'], $args['input'], $hashes, Auth::user(), $args['latestId'] ?? null );
    }
}
