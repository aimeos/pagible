<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Models\Page;
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
        $start = Watch::start( 'cms.theme.watch', Searched::class );

        $vals = $request->validate( [
            'q' => 'required|string|min:' . (int) config( 'cms.search.min', 2 ) . '|max:200',
            'size' => 'integer|between:5,100',
        ] );

        $lang = (string) ( $request->locale ?? app()->getLocale() );

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \Aimeos\Cms\Models\Page> $paginator */
        $paginator = Page::search( $vals['q'] )
            ->query( fn( $q ) => $q->select( 'cms_pages.id', 'domain', 'path', 'lang', 'title', 'meta' )->withGlobalScope( 'status', new Status ) )
            ->where( 'domain', $domain )
            ->where( 'lang', $lang )
            ->searchFields( 'content' )
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

        Watch::dispatchWhen( 'cms.theme.watch', Searched::class, fn() => new Searched(
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
