<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Facades\Auth;
use Aimeos\AnalyticsBridge\Facades\Analytics;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;


class Queries extends Tool
{
    public function __construct()
    {
        $this->as( 'google-queries' )
            ->for( 'Returns the queries entered by users, including impressions, clicks, click-through rates (ctr), and position in Google search results to analyse and optimize the page specified by the URL for SEO.' )
            ->withStringParameter( 'domain', 'The domain of the page to get the user queries for, e.g., "example.com".' )
            ->withStringParameter( 'path', 'The relative path of the page to get the user queries for, e.g., "blog/laravel-cms".' )
            ->using( $this );
    }


    public function __invoke( string $domain, string $path ): string
    {
        if( !Permission::can( 'page:view', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $url = 'https://' . $domain . '/' . ltrim( $path, '/' );
        $queries = Analytics::queries( $url );

        foreach( $queries as &$entry )
        {
            // Rename 'key' to 'query' for better understanding by the LLM
            $entry['query'] = $entry['key'];
            unset( $entry['key'] );
        }

        return response()->json( $queries );
    }
}
