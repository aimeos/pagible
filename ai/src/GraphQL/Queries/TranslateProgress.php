<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Queries;

use Aimeos\Cms\Translation;


final class TranslateProgress
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{total: int, done: int, failed: int}|null
     */
    public function __invoke( $rootValue, array $args ) : ?array
    {
        return Translation\Pages::progress( $args['batch'] );
    }
}
