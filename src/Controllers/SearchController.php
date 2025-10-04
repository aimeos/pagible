<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Content;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class SearchController extends Controller
{
    /**
     * Returns the found pages for the given search term.
     *
     * @param Request $request The current HTTP request instance
     * @param string $domain Requested domain
     * @return Response Response of the controller action
     */
    public function index( Request $request, string $domain = '' )
    {
        $content = Content::search( $request->search )
            ->where( 'lang', $request->locale ?? app()->getLocale() )
            ->where( 'domain', $domain )
            ->get();

        return response()->json( $content );
    }
}
