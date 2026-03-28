<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Page;
use Illuminate\Http\Request;


class Blog
{
    /**
     * Returns the blog articles
     *
     * @param \Illuminate\Http\Request $request
     * @param \Aimeos\Cms\Models\Page $page
     * @param object $item
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, \Aimeos\Cms\Models\Page>
     */
    public function __invoke( Request $request, Page $page, object $item ): \Illuminate\Pagination\LengthAwarePaginator
    {
        /** @phpstan-ignore property.notFound */
        $sort = @$item->data?->order ?: '-id';
        $order = $sort[0] === '-' ? substr( $sort, 1 ) : $sort;
        $dir = $sort[0] === '-' ? 'desc' : 'asc';

        $builder = Page::where( 'type', 'blog' )->orderBy( $order, $dir );

        /** @phpstan-ignore property.notFound */
        if( $pid = @$item->data?->{'parent-page'}?->value ) {
            $builder->where( 'parent_id', $pid );
        }

        if( \Aimeos\Cms\Permission::can( 'page:view', $request->user() ) ) {
            $builder->whereHas('latest', function( $builder ) {
                $builder->where( 'data->status', 1 );
            } );
        } else {
            $builder->where( 'status', 1 );
        }

        $attr = ['id', 'lang', 'path', 'name', 'title', 'to', 'domain', 'content', 'created_at'];

        /** @phpstan-ignore property.notFound */
        return $builder->paginate( @$item->data?->limit ?: 10, $attr, 'p' )
            ->through( function( $item ) {
                $item->content = collect( (array) $item->content )->filter( fn( $item ) => $item->type === 'article' );
                $item->setRelation( 'files', Utils::files( $item ) );
                return $item;
            } );
    }
}
