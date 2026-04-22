<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;


final class SavePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        try {
            $page = Resource::savePage(
                $args['id'],
                $args['input'] ?? [],
                Auth::user(),
                $args['files'] ?? null,
                $args['elements'] ?? null,
                $args['latestId'] ?? null,
            );
        } catch( \InvalidArgumentException $e ) {
            throw new Error( $e->getMessage() );
        }

        Resource::broadcast( $page, Auth::user() );

        return $page;
    }
}
