<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class BulkFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Resource::bulkFile( $args['id'], $args['input'] ?? [], Auth::user() );
    }
}
