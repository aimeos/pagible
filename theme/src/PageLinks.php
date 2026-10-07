<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use Illuminate\Support\Facades\Auth;


/**
 * Resolves "page:<id>" links to the URL of the page in the current language.
 *
 * Links point to the variant in the current language if it's visible, otherwise to
 * the source variant. Links to pages which aren't visible at all resolve to an empty
 * string so the link text is shown without link. Editors get the URLs of the latest
 * versions and links to unpublished pages for previewing.
 */
class PageLinks
{
    /** @var array<string, string> */
    private array $urls = [];


    /**
     * Tests if the value is a page link.
     *
     * @param string|null $url URL or "page:<id>" link
     * @return bool TRUE if it's a page link, FALSE if not
     */
    public static function is( ?string $url ) : bool
    {
        return is_string( $url ) && preg_match( '/^page:[A-Za-z0-9\-]+$/', trim( $url ) ) === 1;
    }


    /**
     * Returns the URL for a "page:<id>" link.
     *
     * @param string $url Page link, e.g. "page:0197..."
     * @return string Page URL or empty string if the page isn't available
     */
    public function url( string $url ) : string
    {
        $id = substr( trim( $url ), 5 );
        return $this->resolve( [$id] )[$id] ?? '';
    }


    /**
     * Returns the URLs for the given page IDs in one query.
     *
     * @param array<int, string> $ids Page IDs
     * @return array<string, string> Page URLs or empty strings by page ID
     */
    public function resolve( array $ids ) : array
    {
        $user = Auth::user();
        $editor = Permission::can( 'page:view', $user );
        $lang = (string) app()->getLocale();
        $prefix = Tenancy::value() . '|' . $lang . '|' . (int) $editor . '|';

        $missing = array_values( array_filter( array_unique( $ids ), fn( $id ) => !isset( $this->urls[$prefix . $id] ) ) );

        if( !empty( $missing ) )
        {
            $query = Page::select( 'id', 'tenant_id', 'lang', 'source', 'path', 'domain', 'to', 'status', 'latest_id' )
                ->whereIn( 'id', $missing );

            if( $editor ) {
                $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] )->fallback( $lang );
            } else {
                $query->visible( $lang )->whereIn( 'status', [1, 2] );
            }

            $pages = $query->get()->keyBy( 'id' );

            foreach( $missing as $id ) {
                $this->urls[$prefix . $id] = ( $page = $pages->get( $id ) ) ? cmsroute( $page ) : '';
            }
        }

        $result = [];

        foreach( $ids as $id ) {
            $result[$id] = $this->urls[$prefix . $id];
        }

        return $result;
    }
}
