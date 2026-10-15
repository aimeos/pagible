<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource\BulkResult;
use Aimeos\Cms\Resource\Elements;
use Illuminate\Support\Facades\Auth;


final class BulkElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return BulkResult
     */
    public function __invoke( $rootValue, array $args ) : BulkResult
    {
        return Elements::bulk( $args['id'], $args['input'] ?? [], Auth::user() );
    }
}
