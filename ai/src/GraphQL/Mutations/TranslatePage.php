<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Jobs\TranslatePage as Job;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
        $langs = array_values( array_unique( array_map( 'strval', $args['lang'] ) ) );
        $ids = Page::whereIn( 'id', array_unique( $args['id'] ) )->orderBy( NestedSet::LFT )->pluck( 'id' );
        $user = Auth::user();
        $batch = (string) Str::uuid();

        Job::batch( $batch, $ids->count() * count( $langs ) );

        foreach( $ids as $id )
        {
            foreach( $langs as $lang ) {
                Job::dispatch( (string) $id, $lang, Tenancy::value(), $user?->getAuthIdentifier(), $batch );
            }
        }

        return ['id' => $batch, 'total' => $ids->count() * count( $langs )];
    }
}
