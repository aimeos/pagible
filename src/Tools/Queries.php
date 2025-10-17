<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Prism\Prism\Tool;
use Aimeos\Cms\Models\Page;


class Queries extends Tool
{
    public function __construct()
    {
        $this->as( 'google-queries' )
            ->for( 'Returns the queries entered by users, including impressions, clicks, click-through rates (ctr), and position in Google search results to analyse and optimize the page specified by the URL for SEO.' )
            ->withStringParameter( 'url', 'The URL of the page to get the user queries for, e.g., "https://example.com/blog".' )
            ->using( $this );
    }


    public function __invoke( string $url ): string
    {
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
