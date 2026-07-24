<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Illuminate\Http\Request;


class Blog
{
    protected string $type = 'blog';
    protected string $element = 'article';


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
        $sort = $item->data->order ?? '-id';
        $order = $sort[0] === '-' ? substr( $sort, 1 ) : $sort;
        $dir = $sort[0] === '-' ? 'desc' : 'asc';

        $editor = \Aimeos\Cms\Permission::can( 'page:view', $request->user() );

        $with = $editor ? ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'versionable_id', 'aux' )] : [];

        $builder = Page::where( 'type', $this->type )->with( $with )->orderBy( $order, $dir );

        if( $pid = $item->data->{'parent-page'}->value ?? null ) {
            $builder->where( 'parent_id', $pid );
        }

        if( $editor ) {
            $builder->whereLatest( ['status' => 1] );
        } else {
            $builder->where( 'status', 1 );
        }

        $attr = ['id', 'lang', 'path', 'name', 'title', 'to', 'domain', 'content', 'created_at', 'latest_id'];
        $pages = $builder->paginate( $item->data->limit ?? 10, $attr, 'p' );

        // The list shows the first element's image per page, taken from the draft content
        // for editors and the published content otherwise. The file IDs come from that element's
        // "files" list (populated for every writer in Validation), and only those files are loaded
        // in one query, so a blog page with many images doesn't pull its whole file set.
        $fileIds = function( $page ) use ( $editor ) {
            $content = $editor ? ( $page->latest?->aux->content ?? $page->content ) : $page->content;
            $article = collect( (array) $content )->first( fn( $el ) => ( $el->type ?? null ) === $this->element );
            return $article ? (array) ( $article->files ?? [] ) : [];
        };

        $ids = $pages->getCollection()->flatMap( $fileIds )->filter()->unique()->values()->all();

        $files = $ids
            ? File::whereIn( 'cms_files.id', $ids )->get( ['cms_files.id', 'cms_files.tenant_id', 'name', 'mime', 'path', 'previews', 'description'] )->keyBy( 'id' )
            : collect();

        $pages->getCollection()->each( function( $page ) use ( $files, $fileIds, $editor ) {
            $used = collect( $fileIds( $page ) )->mapWithKeys( fn( $id ) => [$id => $files->get( $id )] )->filter();

            $page->setRelation( 'files', $used );
            $editor && $page->latest ? $page->latest->setRelation( 'files', $used ) : null;
        } );

        return $pages;
    }
}
