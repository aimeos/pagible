<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class SaveFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        return Resource::saveFile(
            $args['id'],
            $args['input'] ?? [],
            Auth::user(),
            $args['latestId'] ?? null,
            $args['file'] ?? null,
            $args['preview'] ?? null,
        );
    }
}
