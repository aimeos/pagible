<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Actions;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Http\Request;


class Blog
{
    protected string $type = 'blog';
    protected string $element = 'article';


    /**
     * Returns the blog articles
     *
     * @param \Illuminate\Http\Request $request
     * @param \Aimeos\Cms\Models\PageView $page
     * @param object $item
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, \Aimeos\Cms\Models\PageView>
     */
    public function __invoke( Request $request, PageView $page, object $item ): \Illuminate\Pagination\LengthAwarePaginator
    {
        // newest first by default, sorting by the creation date of the variants uses their index
        $sort = $item->data->order ?? '-created_at';
        $order = $sort[0] === '-' ? substr( $sort, 1 ) : $sort;
        $dir = $sort[0] === '-' ? 'desc' : 'asc';

        $editor = \Aimeos\Cms\Permission::can( 'page:view', $request->user() );

        $with = $editor ? ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'versionable_id', 'aux' )] : [];

        $attr = ['id', 'lang', 'path', 'name', 'title', 'to', 'domain', 'content', 'created_at', 'latest_id'];

        $filter = function( $builder ) use ( $item, $page, $editor ) {
            $builder->whereIn( 'status', [1, 2] );

            if( $pid = $item->data->{'parent-page'}->value ?? null ) {
                $builder->where( 'parent_id', $pid );
            } else {
                $builder->where( 'domain', (string) $page->domain );
            }

            $editor ? $builder->whereLatest( ['type' => $this->type] ) : $builder->where( 'type', $this->type );
            return $builder;
        };

        // list the articles in the language of the current page, see "cms.translate.fallback"
        if( is_string( $page->lang ) && $page->lang !== '' && PageView::fallbackToSource() ) {
            // only the listed columns and those used by the scopes and the order are copied
            $cols = array_values( array_unique( [...$attr, 'variant_id', 'tenant_id', 'deleted_at', 'status', $order] ) );
            $builder = $this->fallback( $filter, $page->lang, $editor, $cols );
        } else {
            $page->localize( $builder = PageView::query() );
            $filter( $builder );
        }

        $builder->with( $with )->orderBy( $order, $dir );

        $pages = $builder->paginate( $item->data->limit ?? 10, $attr, 'p' );
        $request->attributes->set( 'cms.pagination', $pages->currentPage() );

        // The list shows the first element's image per page, taken from the draft content
        // for editors and the published content otherwise. The file IDs come from that element's
        // "files" list (populated for every writer in Validation), and only those files are loaded
        // in one query, so a blog page with many images doesn't pull its whole file set.
        $fileIds = function( $page ) use ( $editor ) {
            $latest = $editor ? $page->getRelation( 'latest' ) : null;
            $content = $latest instanceof Version ? ( $latest->aux->content ?? $page->content ) : $page->content;
            $article = collect( (array) $content )->first( fn( $el ) => ( $el->type ?? null ) === $this->element );
            return $article ? (array) ( $article->files ?? [] ) : [];
        };

        $ids = $pages->getCollection()->flatMap( $fileIds )->filter()->unique()->values()->all();

        $files = $ids
            ? File::whereIn( 'cms_files.id', $ids )->get( ['cms_files.id', 'cms_files.tenant_id', 'disk', 'name', 'mime', 'path', 'previews', 'description'] )->keyBy( 'id' )
            : collect();

        $pages->getCollection()->each( function( $page ) use ( $files, $fileIds, $editor ) {
            $used = collect( $fileIds( $page ) )->mapWithKeys( fn( $id ) => [$id => $files->get( $id )] )->filter();

            $page->setRelation( 'files', $used );
            $latest = $editor ? $page->getRelation( 'latest' ) : null;
            $latest instanceof Version ? $latest->setRelation( 'files', $used ) : null;
        } );

        return $pages;
    }


    /**
     * Returns the query for the articles in the language or in the source language if they aren't translated.
     *
     * The fallback condition of the page view can't use an index, so the articles in the language and the
     * untranslated ones in the source language are read by two indexed queries combined by UNION ALL.
     * Editors see the source variants of pages without a variant in the language, visitors also of pages
     * whose variant isn't enabled, like PageQuery::localized() does.
     *
     * @param \Closure $filter Adds the conditions for the articles to the passed query
     * @param string $lang Language code
     * @param bool $editor TRUE for editors, FALSE for visitors
     * @param list<string> $cols Columns of the combined rows
     * @return \Aimeos\Cms\Query\PageQuery<PageView> Query for the articles
     */
    protected function fallback( \Closure $filter, string $lang, bool $editor, array $cols ) : \Aimeos\Cms\Query\PageQuery
    {
        $translated = $filter( PageView::select( $cols )->language( $lang )->when( !$editor, fn( $q ) => $q->where( 'status', '<>', 0 ) ) );

        $source = $filter( PageView::select( $cols )->language( null )->where( 'lang', '<>', $lang )
            ->whereNotExists( fn( $q ) => $q->selectRaw( '1' )->from( 'cms_page_variants as m' )
                ->whereColumn( 'm.page_id', 'cms_page_view.id' )
                ->where( 'm.lang', $lang )
                ->whereNull( 'm.deleted_at' )
                ->when( !$editor, fn( $q ) => $q->where( 'm.status', '<>', 0 ) ) ) );

        // the scopes are applied to both parts, from() also removes the variant condition of the outer query
        return PageView::query()->from( $translated->toBase()->unionAll( $source->toBase() ), 'cms_page_view' );
    }
}
