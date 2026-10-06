<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Metrics as PageMetrics;


final class Metrics
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke( $rootValue, array $args ): array
    {
        return PageMetrics::get( $args['url'], $args['days'] ?? 30 );
    }
}
