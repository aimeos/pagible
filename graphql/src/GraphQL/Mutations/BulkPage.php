<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource\BulkResult;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Support\Facades\Auth;


final class BulkPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return BulkResult
     */
    public function __invoke( $rootValue, array $args ) : BulkResult
    {
        if( isset( $args['lang'] ) ) {
            return Variants::bulk( $args['id'], $args['lang'], $args['input'] ?? [], Auth::user(), $args['descendants'] ?? false );
        }

        return Pages::bulk( $args['id'], $args['input'] ?? [], Auth::user(), $args['descendants'] ?? false );
    }
}
