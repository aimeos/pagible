<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Jobs\TranslatePage as Job;
use Aimeos\Cms\Models\Page;
use Illuminate\Support\Facades\Auth;
use Aimeos\Nestedset\NestedSet;


final class TranslatePage
{
    /**
     * Queues one translation job per page and language in tree order.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{id: string, total: int}
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $ids = Page::whereIn( 'id', array_unique( $args['id'] ) )->orderBy( NestedSet::LFT )->pluck( 'id' );
        return Job::dispatchBatch( $ids, array_map( 'strval', $args['lang'] ), Auth::user()?->getAuthIdentifier() );
    }
}
