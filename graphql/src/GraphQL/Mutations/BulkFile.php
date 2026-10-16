<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource\BulkResult;
use Aimeos\Cms\Facades\Files;
use Illuminate\Support\Facades\Auth;


final class BulkFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return BulkResult
     */
    public function __invoke( $rootValue, array $args ) : BulkResult
    {
        return Files::bulk( $args['id'], $args['input'] ?? [], Auth::user() );
    }
}
