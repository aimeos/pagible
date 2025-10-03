<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Prism\Prism\Tool;
use Aimeos\Cms\Models\Page;


class Pages extends Tool
{
    public function __construct()
    {
        $this->as( 'search-pages' )
            ->for( 'Searches the page tree for pages containing a keyword. Returns up to 10 matching pages as JSON array.' )
            ->withStringParameter( 'term', 'Search keyword, e.g., "blog", "product", or "FAQ". One word or page path only.')
            ->withStringParameter( 'lang', 'ISO language code of the pages to search for, e.g. "en" or "en-US".')
            ->using( $this );
    }


    public function __invoke( string $term, string $lang ): string
    {
        $result = Page::withoutTrashed()
            ->where( function( $builder ) use ( $lang, $term ) {
                $builder->whereAny( ['content', 'meta', 'name', 'path', 'title'], 'like', '%' . $term . '%' )
                ->where( 'lang', $lang );
            } )
            ->orWhereHas('versions', function( $builder ) use ( $lang, $term  ) {
                $builder->where( 'data', 'like', '%' . $term . '%' )
                    ->where( 'lang', $lang );
            } )
            ->take( 10 )
            ->get()
            ->map( fn( $item ) => $item->toArray() + ['url' => route( 'cms.page', ['path' => $item->path] )] );

        return response()->json( $result );
    }
}
