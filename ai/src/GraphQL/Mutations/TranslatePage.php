<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Translation;
use Illuminate\Support\Facades\Auth;


final class TranslatePage
{
    /**
     * Queues the missing and stale page variants for translation, see Translation\Pages::dispatch().
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{id: string, total: int, done: int, failed: int}
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Translation\Pages::dispatch( $args['id'], $args['lang'], Auth::user() );
    }
}
