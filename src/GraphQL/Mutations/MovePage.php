<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class MovePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        if( !Permission::can( 'page:move', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        return Cache::lock( 'cms_pages_' . \Aimeos\Cms\Tenancy::value(), 30 )->get( function() use ( $args ) {
            return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

                /** @var Page $page */
                $page = Page::withTrashed()->findOrFail( $args['id'] );
                $page->editor = Auth::user()->name ?? request()->ip();

                if( isset( $args['ref'] ) ) {
                    /** @var Page $ref */
                    $ref = Page::withTrashed()->findOrFail( $args['ref'] );
                    $page->beforeNode( $ref );
                } elseif( isset( $args['parent'] ) ) {
                    /** @var Page $parent */
                    $parent = Page::withTrashed()->findOrFail( $args['parent'] );
                    $page->appendToNode( $parent );
                } else {
                    $page->makeRoot();
                }

                $page->save();

                return $page;
            }, 3 );
        } );
    }
}
