<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class MovePage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        if( !Permission::can( 'page:move', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $page = Page::withTrashed()->findOrFail( $args['id'] );
        $page->editor = Auth::user()?->name ?? request()->ip();

        if( isset( $args['ref'] ) ) {
            $page->beforeNode( Page::withTrashed()->findOrFail( $args['ref'] ) );
        }
        elseif( isset( $args['parent'] ) ) {
            $page->appendToNode( Page::withTrashed()->findOrFail( $args['parent'] ) );
        }
        else {
            DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( fn() => $page->saveAsRoot(), 3 );
            return $page;
        }

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( fn() => $page->save(), 3 );

        return $page;
    }
}
