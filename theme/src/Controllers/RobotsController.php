<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;


class RobotsController extends Controller
{
    /**
     * Maximum robots.txt response size supported by common crawlers.
     */
    protected const MAX_BYTES = 500 * 1024;


    /**
     * Returns configured robots.txt rules and the CMS sitemap locations.
     *
     * @param string $domain Requested domain
     * @return Response Plain-text robots response
     */
    public function index( string $domain = '' ) : Response
    {
        $body = $this->body( $domain, $this->text( $domain ) );

        if( strlen( $body ) > static::MAX_BYTES ) {
            $body = $this->body( $domain, null );
        }

        return response( $body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ] );
    }


    /**
     * Builds the robots.txt response body.
     */
    protected function body( string $domain, ?string $text ) : string
    {
        $parts = [];

        if( $text !== null ) {
            $parts[] = rtrim( $text, "\n" );
        }

        $lines = [];

        foreach( ['cms.sitemap', 'cms.sitemap.news'] as $route )
        {
            $url = cmsroute( $route, [], $domain ?: null );
            $pattern = '/^\h*(?i:Sitemap)\h*:\h*' . preg_quote( $url, '/' ) . '(?=\h|$)/mu';

            if( preg_match( $pattern, $text ?? '' ) !== 1 ) {
                $lines[] = 'Sitemap: ' . $url;
            }
        }

        if( $lines ) {
            $parts[] = implode( "\n", $lines );
        }

        return implode( "\n\n", $parts ) . "\n";
    }


    /**
     * Normalizes valid robots.txt input and rejects unsafe values.
     */
    protected function clean( mixed $value ) : ?string
    {
        if( !is_string( $value ) ) {
            return null;
        }

        if( str_starts_with( $value, "\xEF\xBB\xBF" ) ) {
            $value = substr( $value, 3 );
        }

        $value = str_replace( ["\r\n", "\r"], "\n", $value );

        if( strlen( $value ) > static::MAX_BYTES || trim( $value ) === ''
            || preg_match( '/\A[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*\z/u', $value ) !== 1
        ) {
            return null;
        }

        return $value;
    }


    /**
     * Returns the published robots.txt config from the first matching root page.
     *
     * @param string $domain Requested domain, empty when multi-domain routing is disabled
     * @return string|null Normalized robots.txt content
     */
    protected function text( string $domain ) : ?string
    {
        $query = Nav::query()
            ->select( 'config' )
            ->whereNull( 'parent_id' )
            ->whereIn( 'status', [1, 2] )
            ->defaultOrder();

        if( $domain !== '' ) {
            $query->where( 'domain', $domain );
        }

        foreach( $query->cursor() as $page )
        {
            if( ( $text = $this->clean( $page->config->{'robots-txt'}->data->text ?? null ) ) !== null ) {
                return $text;
            }
        }

        return null;
    }
}
