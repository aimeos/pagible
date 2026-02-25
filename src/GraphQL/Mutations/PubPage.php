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


final class PubPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'page:publish', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            $items = Page::withTrashed()->whereIn( 'id', $args['id'] )->get();
            $editor = Auth::user()->name ?? request()->ip();

            foreach( $items as $item )
            {
                /** @var Page $item */
                if( $latest = $item->latest )
                {
                    if( $args['at'] ?? null )
                    {
                        $latest->publish_at = $args['at'];
                        $latest->editor = $editor;
                        $latest->save();
                        continue;
                    }

                    $item->publish( $latest );
                }
            }

            return $items->all();
        }, 3 );
    }
}
