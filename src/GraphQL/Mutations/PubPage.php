<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Models\Page;


final class PubPage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $items = Page::withTrashed()->whereIn( 'id', $args['id'] )->get();
        $editor = Auth::user()?->name ?? request()->ip();

        foreach( $items as $item )
        {
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
    }
}
