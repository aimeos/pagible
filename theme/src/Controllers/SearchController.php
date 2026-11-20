<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Scout;
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
        $start = hrtime( true );

        $vals = $request->validate( [
            'q' => 'required|string|min:' . (int) config( 'cms.theme.min-search' ) . '|max:200',
            'size' => 'integer|between:5,100',
            'locale' => 'nullable|string|cms_locale',
        ] );

        $lang = (string) ( $vals['locale'] ?? app()->getLocale() );

        $external = Scout::usesExternalSearch();
        $builder = PageView::search( $vals['q'] )
            ->query( function( $q ) use ( $domain, $lang ) {
                $q->select( 'cms_page_view.id', 'cms_page_view.variant_id', 'cms_page_view.tenant_id', 'domain', 'path', 'lang', 'title', 'meta' )
                    ->active()
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

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \Aimeos\Cms\Models\PageView> $paginator */
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

        $duration = Watch::duration( $start );
        $tenant = Tenancy::value();

        // Keep the rich audit payload package-local. The neutral metric event only
        // carries aggregation-safe fields, and each consumer samples independently.
        Watch::dispatch( CmsSearch::class, fn() => new CmsSearch(
            query: (string) $vals['q'],
            results: $paginator->total(),
            page: $paginator->currentPage(),
            durationMs: $duration,
            domain: $domain,
            lang: $lang,
            tenant: $tenant,
        ) );

        Watch::observe(
            source: 'search',
            action: 'theme:search',
            durationMs: $duration,
            tenant: $tenant,
            dimensions: ['domain' => $domain, 'lang' => $lang],
            sample: true,
        );

        return response()->json( $content );
    }
}
