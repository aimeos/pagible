<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Facades\Files;
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
        return Files::relocateMany( $args['id'], $args['disk'], Auth::user() );
    }
}
