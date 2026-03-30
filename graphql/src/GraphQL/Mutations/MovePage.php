<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Auth;


final class MovePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        return Utils::lockedTransaction( function() use ( $args ) {

                /** @var Page $page */
                $page = Page::withTrashed()->findOrFail( $args['id'] );
                $page->editor = Utils::editor( Auth::user() );

                Resource::position( $page, $args['ref'] ?? null, $args['parent'] ?? null, true );
                Page::withoutSyncingToSearch( fn() => $page->save() );

                return $page;
        } );
    }
}
