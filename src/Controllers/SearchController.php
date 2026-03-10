<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Page;
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
        $vals = $request->validate( [
            'search' => 'required|string|min:3',
            'size' => 'integer|between:5,100',
        ] );

        $content = Page::search( $vals['search'] )
            ->where( 'domain', $domain )
            ->where( 'lang', $request->locale ?? app()->getLocale() )
            ->paginate( $vals['size'] ?? 25 )
            ->through( fn( $item ) => [
                'domain' => $item->domain ?? '',
                'path' => $item->path ?? '',
                'lang' => $item->lang ?? '',
                'title' => $item->title ?? '',
                'content' => $item->meta->{'meta-tags'}->data->description ?? '',
            ] );

        return response()->json( $content );
    }
}
