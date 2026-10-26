<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Jobs\TranslatePage as Job;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Illuminate\Support\Facades\Auth;
use Aimeos\Nestedset\NestedSet;


final class TranslatePage
{
    /**
     * Queues one translation job per page and language in tree order.
     *
     * Only missing and stale variants are translated, the pages in their source language
     * and up to date variants are skipped.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{id: string, total: int}
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $user = Auth::user();
        $langs = array_map( 'strval', $args['lang'] );

        Job::authorize( $user, $langs );

        $ids = PageView::whereIn( 'id', array_unique( $args['id'] ) )->orderBy( NestedSet::LFT )->pluck( 'id' )->all();

        return Job::dispatchPending( $ids, $langs, $user?->getAuthIdentifier(), Permission::can( 'variant:add', $user ) );
    }
}
