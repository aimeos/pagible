<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Scopes\Status;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;


class SitemapController extends Controller
{
    /**
     * Maximum number of URLs per sitemap file (per sitemaps.org protocol).
     */
    protected const URLS_PER_SITEMAP = 50000;


    /**
     * Streams the sitemap entry point.
     *
     * Returns a single `<urlset>` document if the total URL count fits within
     * {@see self::URLS_PER_SITEMAP}, otherwise returns a `<sitemapindex>`
     * referencing chunked sitemap files served by {@see self::chunk()}.
     *
     * @return Response XML response (`application/xml`)
     */
    public function index() : Response
    {
        /** @var object{cnt: int, max_updated: string|null} $agg */
        $agg = $this->query()->selectRaw( 'COUNT(*) as cnt, MAX(updated_at) as max_updated' )->first();

        if( $agg->cnt <= static::URLS_PER_SITEMAP ) {
            return $this->urlset();
        }

        return $this->sitemapIndex( (int) $agg->cnt, $agg->max_updated );
    }


    /**
     * Streams a single sitemap chunk for large catalogs.
     *
     * Each chunk contains up to {@see self::URLS_PER_SITEMAP} URLs ordered by
     * id. Aborts with HTTP 404 if `$page` is outside the available range.
     *
     * @param int $page One-based chunk index
     * @return StreamedResponse `<urlset>` XML response
     */
    public function chunk( int $page ) : StreamedResponse
    {
        if( $page < 1 ) {
            abort( 404 );
        }

        $offset = ( $page - 1 ) * static::URLS_PER_SITEMAP;

        if( $offset > 0 && $this->query()->count() <= $offset ) {
            abort( 404 );
        }

        return $this->urlset( $offset, static::URLS_PER_SITEMAP );
    }


    /**
     * Returns the shared base query for published, non-redirect navigation entries.
     *
     * Uses the underlying query builder (no Eloquent hydration) so callers can
     * stream rows efficiently via `cursor()`. Tenancy and soft-delete global
     * scopes are inherited from the `Nav` model and compiled into the builder
     * by `toBase()`.
     *
     * @return \Illuminate\Database\Query\Builder Base query with status scope and `to` filter applied
     */
    protected function query() : \Illuminate\Database\Query\Builder
    {
        return Nav::withGlobalScope( 'status', new Status )
            ->where( function( $q ) {
                $q->whereNull( 'to' )->orWhere( 'to', '' );
            } )
            ->toBase();
    }


    /**
     * Streams a `<urlset>` XML document.
     *
     * When `$limit` is null all rows are streamed (single-file mode); otherwise
     * the result is sliced via `ORDER BY id LIMIT/OFFSET` for chunked output.
     * The route URL is resolved once with placeholders and substituted per row
     * to avoid the per-iteration cost of Laravel's URL generator.
     *
     * @param int|null $offset Row offset for chunked output, ignored when `$limit` is null
     * @param int|null $limit  Maximum rows to stream, or null for all rows
     * @return StreamedResponse `<urlset>` XML response
     */
    protected function urlset( ?int $offset = null, ?int $limit = null ) : StreamedResponse
    {
        $tz = new \DateTimeZone( config('app.timezone') ?: 'UTC' );
        $multidomain = config( 'cms.multidomain' ) ? ['domain' => '__CMS_DOMAIN__'] : [];
        $template = route( 'cms.page', $multidomain + ['path' => '__CMS_PATH__'] );

        $query = $this->query()->select( 'path', 'domain', 'updated_at' );

        if( $limit !== null ) {
            $query->orderBy( 'id' )->offset( (int) $offset )->limit( $limit );
        }

        return response()->stream( function() use ( $tz, $template, $query ) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $i = 0;
            foreach( $query->cursor() as $page )
            {
                $lastmod = $page->updated_at
                    ? ( new \DateTimeImmutable( $page->updated_at, $tz ) )->format( \DateTimeInterface::ATOM )
                    : '';

                $path = (string) $page->path;
                $encodedPath = preg_match( '/[^A-Za-z0-9\/._~-]/', $path )
                    ? implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) )
                    : $path;
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
        }, 200, ['Content-Type' => 'application/xml', 'Cache-Control' => 'public, max-age=300'] );
    }


    /**
     * Streams a `<sitemapindex>` XML document for catalogs above the URL limit.
     *
     * Emits `ceil($count / URLS_PER_SITEMAP)` `<sitemap>` entries pointing at
     * the chunked sitemap files served by {@see self::chunk()}. A single global
     * `MAX(updated_at)` is used as `<lastmod>` for every entry — portable,
     * one extra query, and acceptable for crawlers.
     *
     * @param int $count Total URL count from {@see self::query()}
     * @return Response `<sitemapindex>` XML response
     */
    protected function sitemapIndex( int $count, ?string $maxUpdated ) : Response
    {
        $lastmod = '';
        $entries = [];
        $pages = (int) ceil( $count / static::URLS_PER_SITEMAP );

        if( $maxUpdated )
        {
            $tz = new \DateTimeZone( config('app.timezone') ?: 'UTC' );
            $lastmod = ( date_create( $maxUpdated, $tz ) ?: new \DateTime( 'now', $tz ) )->format( \DateTimeInterface::ATOM );
        }

        for( $n = 1; $n <= $pages; $n++ )
        {
            $route = route( 'cms.sitemap.chunk', ['page' => $n] );
            $entries[] = '<sitemap><loc>' . $route . '</loc><lastmod>' . $lastmod . '</lastmod></sitemap>';
        }

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
             . implode( '', $entries ) .
            '</sitemapindex>',
            200, ['Content-Type' => 'application/xml', 'Cache-Control' => 'public, max-age=300']
        );
    }
}
