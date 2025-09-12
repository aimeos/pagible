<?php

namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Scopes\Status;
use Illuminate\Routing\Controller;


class SitemapController extends Controller
{
    public function index()
    {
        return response()->stream( function() {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            Nav::withGlobalScope('status', new Status)->chunkById( 100, function( $pages ) {

                foreach( $pages as $page )
                {
                    if( !$page->to )
                    {
                        echo '<url>';
                        echo '<loc>' . route('cms.page', ['path' => $page->path] + (config('cms.multidomain') ? ['domain' => $page->domain] : [])) . '</loc>';
                        echo '<lastmod>' . optional($page->updated_at)->toAtomString() . '</lastmod>';
                        echo '</url>';
                    }
                }
                flush();
            });

            echo '</urlset>';
        }, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
