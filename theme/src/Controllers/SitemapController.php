<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Scopes\Status;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;


class SitemapController extends Controller
{
    public function index() : StreamedResponse
    {
        return response()->stream( function() {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $multidomain = (bool) config('cms.multidomain');
            $query = Nav::withGlobalScope('status', new Status)
                ->select( 'path', 'domain', 'to', 'updated_at' )
                ->toBase();

            $i = 0;
            foreach( $query->cursor() as $page )
            {
                if( $page->to ) {
                    continue;
                }

                $lastmod = $page->updated_at ? Carbon::parse($page->updated_at)->toAtomString() : '';
                $loc = route('cms.page', ['path' => $page->path] + ($multidomain ? ['domain' => $page->domain] : []));

                echo '<url>';
                echo '<loc><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $loc) . ']]></loc>';
                echo '<lastmod><![CDATA[' . $lastmod . ']]></lastmod>';
                echo '</url>';

                if( ++$i % 100 === 0 ) {
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
