<?php

namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Page;
use Illuminate\Http\Request;


class Blog
{
    public function __invoke( Request $request, Page $page, object $item )
    {
        $pid = @$item->data?->{'parent-page'}?->value ?: $page->id;
        $sort = @$item->data?->order ?: '-id';

        $order = $sort[0] === '-' ? substr( $sort, 1 ) : $sort;
        $dir = $sort[0] === '-' ? 'desc' : 'asc';

        $builder = Page::where( 'parent_id', $pid )->orderBy( $order, $dir );

        if( \Aimeos\Cms\Permission::can( 'page:view', $request->user() ) ) {
            $builder->whereHas('latest', function( $builder ) {
                $builder->where( 'data->status', 1 );
            } );
        } else {
            $builder->where( 'status', 1 );
        }

        $attr = ['id', 'lang', 'path', 'name', 'title', 'to', 'domain', 'content'];

        return $builder->paginate( @$item->data?->limit ?? 10, $attr, 'p' )
            ->through( function( $item ) {
                $item->content = collect( $item->content )->filter( fn( $item ) => $item->type === 'article' );
                $item->setRelation( 'files', Utils::files( $item ) );
                return $item;
            } );
    }
}
