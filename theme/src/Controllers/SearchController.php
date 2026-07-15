<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class SearchController extends Controller
{
    /**
     * Returns the found pages for the given search term.
     *
     * @param Request $request The current HTTP request instance
     * @param string $domain Requested domain
     * @return \Illuminate\Http\JsonResponse Response of the controller action
     */
    public function index( Request $request, string $domain = '' )
    {
        $start = Watch::start( 'cms.theme.watch', CmsSearch::class );

        $vals = $request->validate( [
            'q' => 'required|string|min:' . (int) config( 'cms.search.min', 2 ) . '|max:200',
            'size' => 'integer|between:5,100',
        ] );

        $lang = (string) ( $request->locale ?? app()->getLocale() );

        $external = Scout::usesExternalSearch();
        $builder = Page::search( $vals['q'] )
            ->query( function( $q ) use ( $domain, $lang ) {
                $q->select( 'cms_pages.id', 'cms_pages.tenant_id', 'domain', 'path', 'lang', 'title', 'meta' )
                    ->withGlobalScope( 'status', new Status )
                    ->where( 'domain', $domain )
                    ->where( 'lang', $lang )
                    ->wherePublic();
            } )
            ->where( 'domain', $domain )
            ->where( 'lang', $lang )
            ->searchFields( 'content' );

        if( $external ) {
            $builder->where( 'restricted', false );
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \Aimeos\Cms\Models\Page> $paginator */
        $paginator = $builder
            ->paginate( $vals['size'] ?? 25 )
            ->appends( $request->query() );

        $content = $paginator->through( fn( $item ) => [
                'domain' => $item->domain ?? '',
                'path' => $item->path ?? '',
                'lang' => $item->lang ?? '',
                'title' => $item->title ?? '',
                'content' => $item->meta->{'meta-tags'}->data->description ?? '',
                'relevance' => $item->relevance ?? 0,
            ] );

        // Dispatch whenever watch is enabled or a consumer listens; each consumer
        // (CmsSearchPulseRecorder, SearchLogListener) applies its own Watch::sampled(),
        // so both are thinned independently — search is a high-volume read stream.
        Watch::dispatchWhen( 'cms.theme.watch', CmsSearch::class, fn() => new CmsSearch(
            query: (string) $vals['q'],
            results: $paginator->total(),
            page: $paginator->currentPage(),
            durationMs: Watch::duration( $start ),
            domain: $domain,
            lang: $lang,
            tenant: Tenancy::value(),
        ) );

        return response()->json( $content );
    }
}
