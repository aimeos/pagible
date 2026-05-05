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

        $editor = \Aimeos\Cms\Permission::can( 'page:view', $request->user() );

        $with = [
            'files' => fn( $q ) => $q->select( 'cms_files.id', 'name', 'mime', 'path', 'previews' ),
        ];

        if( $editor ) {
            $with['latest'] = fn( $q ) => $q->select( 'id', 'versionable_id', 'aux' );
            $with['latest.files'] = fn( $q ) => $q->select( 'cms_files.id', 'name', 'mime', 'path', 'previews' );
        }

        $builder = Page::where( 'type', 'blog' )->with( $with )->orderBy( $order, $dir );

        /** @phpstan-ignore property.notFound */
        if( $pid = @$item->data?->{'parent-page'}?->value ) {
            $builder->where( 'parent_id', $pid );
        }

        if( $editor ) {
            $builder->whereLatest( ['status' => 1] );
        } else {
            $builder->where( 'status', 1 );
        }

        $attr = ['id', 'lang', 'path', 'name', 'title', 'to', 'domain', 'content', 'created_at'];

        /** @phpstan-ignore property.notFound */
        return $builder->paginate( @$item->data?->limit ?: 10, $attr, 'p' )
            ->through( function( $item ) {
                if( $item->relationLoaded( 'latest' ) && $version = $item->latest ) {
                    $item->content = $version->aux->content ?? $item->content;
                    $item->setRelation( 'files', $version->files ?? $item->files );
                }

                $item->content = (object) collect( (array) $item->content )->filter( fn( $item ) => $item->type === 'article' )->all();
                $item->setRelation( 'files', Utils::files( $item ) );
                return $item;
            } );
    }
}
