<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Scopes\Status;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;


class SitemapController extends Controller
{
    public function index() : StreamedResponse
    {
        $tz = new \DateTimeZone( config('app.timezone') ?: 'UTC' );
        $multidomain = config( 'cms.multidomain' ) ? ['domain' => '__CMS_DOMAIN__'] : [];
        $template = route( 'cms.page', $multidomain + ['path' => '__CMS_PATH__'] );

        return response()->stream( function() use ($tz, $template) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $query = Nav::withGlobalScope('status', new Status)
                ->select( 'path', 'domain', 'updated_at' )
                ->where( function( $q ) {
                    $q->whereNull( 'to' )->orWhere( 'to', '' );
                } )
                ->toBase();

            $i = 0;
            foreach( $query->cursor() as $page )
            {
                $lastmod = $page->updated_at
                    ? date_create_immutable( $page->updated_at, $tz )->format( \DateTimeInterface::ATOM )
                    : '';

                $encodedPath = implode( '/', array_map( 'rawurlencode', explode( '/', (string) $page->path ) ) );
                $loc = str_replace( ['__CMS_PATH__', '__CMS_DOMAIN__'], [$encodedPath, $page->domain ?? ''], $template );

                echo '<url>';
                echo '<loc><![CDATA[' . $loc . ']]></loc>';
                echo '<lastmod><![CDATA[' . $lastmod . ']]></lastmod>';
                echo '</url>';

                if( ++$i % 5000 === 0 ) {
                    flush();
                }
            }

            echo '</urlset>';
            flush();
        }, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
