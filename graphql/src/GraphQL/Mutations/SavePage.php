<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
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
            return Resource::savePage(
                $args['id'],
                $args['input'] ?? [],
                Auth::user(),
                Utils::editor( Auth::user() ),
                $args['files'] ?? [],
                $args['elements'] ?? [],
            );
        } catch( \InvalidArgumentException $e ) {
            throw new Error( $e->getMessage() );
        }
    }
}
