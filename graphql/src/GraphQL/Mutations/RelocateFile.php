<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


final class RelocateFile
{
    /**
     * @param null $rootValue
     * @param array<string, mixed> $args
     * @return Collection<int, \Aimeos\Cms\Models\File>
     */
    public function __invoke( $rootValue, array $args ) : Collection
    {
        return Resource::relocateFiles( $args['id'], $args['disk'], Auth::user() );
    }
}
