<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class BulkPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Resource::bulkPage(
            $args['id'],
            $args['input'] ?? [],
            Auth::user(),
            $args['descendants'] ?? false,
        );
    }
}
