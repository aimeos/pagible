<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
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
     * Maximum number of articles per Google News sitemap.
     */
    protected const NEWS_PER_SITEMAP = 1000;


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
     * Streams the Google News sitemap for articles published in the last two days.
     *
     * @return StreamedResponse News `<urlset>` XML response
     */
    public function news( string $domain = '' ) : StreamedResponse
    {
        $name = $this->xml( $this->name( $domain ) );
        $template = $this->template();
        $tz = new \DateTimeZone( config('app.timezone') ?: 'UTC' );

        $query = $this->query()
            ->where( 'type', 'news' )
            ->where( 'created_at', '>=', now()->subDays( 2 ) )
            ->select( 'path', 'domain', 'lang', 'title', 'created_at', 'meta' )
            ->orderByDesc( 'created_at' )
            ->limit( static::NEWS_PER_SITEMAP );

        return response()->stream( function() use ( $name, $query, $template, $tz ) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';

            foreach( $query->cursor() as $page )
            {
                if( $this->noindex( $page->meta ) ) {
                    continue;
                }

                $date = ( new \DateTimeImmutable( $page->created_at, $tz ) )->format( \DateTimeInterface::ATOM );
                $lang = strtolower( str_replace( '_', '-', $page->lang ?: config( 'app.locale', 'en' ) ) );
                $lang = in_array( $lang, ['zh-cn', 'zh-tw'], true ) ? $lang : explode( '-', $lang )[0];

                echo '<url>';
                echo '<loc><![CDATA[' . $this->location( $page, $template ) . ']]></loc>';
                echo '<news:news><news:publication>';
                echo '<news:name>' . $name . '</news:name>';
                echo '<news:language>' . $this->xml( $lang ) . '</news:language>';
                echo '</news:publication>';
                echo '<news:publication_date>' . $date . '</news:publication_date>';
                echo '<news:title>' . $this->xml( $page->title ) . '</news:title>';
                echo '</news:news></url>';
            }

            echo '</urlset>';
            flush();
        }, 200, ['Content-Type' => 'application/xml', 'Cache-Control' => 'public, max-age=300'] );
    }


    /**
     * Streams a single sitemap chunk for large catalogs.
     *
     * Each chunk contains up to {@see self::URLS_PER_SITEMAP} URLs ordered by
     * id. Aborts with HTTP 404 if `$page` is outside the available range.
     *
     * @param int|string $page One-based chunk index or leading multi-domain route parameter
     * @return StreamedResponse `<urlset>` XML response
     */
    public function chunk( int|string $page ) : StreamedResponse
    {
        $route = request()->route( 'page' );

        if( is_int( $route ) || is_string( $route ) ) {
            $page = $route;
        }

        $page = (int) $page;

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
     * Returns the published website title from the root page configuration.
     */
    protected function name( string $domain ) : string
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
            $name = trim( (string) ( $page->config->website->data->title ?? '' ) );

            if( $name !== '' ) {
                return $name;
            }
        }

        return '';
    }


    /**
     * Determines whether a page opts out of indexing.
     *
     * Inspects the raw `meta` JSON for a `robots` item whose `index` field is
     * set to `noindex`. This filtering is applied in PHP while streaming rows
     * (see {@see self::urlset()}) rather than in SQL, keeping the shared query
     * portable across databases. The unfiltered row count is a safe
     * over-estimate for chunk sizing.
     *
     * @param string|null $meta Raw `meta` JSON from the page row
     * @return bool True if the page carries a `noindex` robots directive
     */
    protected function noindex( ?string $meta ) : bool
    {
        foreach( (array) json_decode( (string) $meta ) as $item )
        {
            if( is_object( $item ) && ( $item->type ?? '' ) === 'robots'
                && is_object( $item->data ?? null ) && ( $item->data->index ?? '' ) === 'noindex'
            ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns the absolute URL for a sitemap row.
     *
     * @param \stdClass $page Raw sitemap row
     * @param string $template Route URL containing page placeholders
     * @return string Absolute page URL
     */
    protected function location( \stdClass $page, string $template ) : string
    {
        $path = (string) $page->path;
        $path = preg_match( '/[^A-Za-z0-9\/._~-]/', $path )
            ? implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) )
            : $path;

        return str_replace( ['__CMS_PATH__', '__CMS_DOMAIN__'], [$path, $page->domain ?? ''], $template );
    }


    /**
     * Returns the shared base query for published, non-redirect navigation entries.
     *
     * Uses the underlying query builder (no Eloquent hydration) so callers can
     * stream rows efficiently via `cursor()`. Tenancy and soft-delete global
     * scopes are inherited from the `Nav` model and compiled into the builder
     * by `toBase()`.
     *
     * @return \Illuminate\Database\Query\Builder Base query with public status, `to` and domain filters applied
     */
    protected function query() : \Illuminate\Database\Query\Builder
    {
        // Sitemaps are publicly cacheable, so their contents must never depend on
        // the authenticated editor exception implemented by the Status scope.
        $query = Nav::whereIn( ( new Nav() )->qualifyColumn( 'status' ), [1, 2] )
            ->where( function( $q ) {
                $q->whereNull( 'to' )->orWhere( 'to', '' );
            } )
            ->wherePublic();

        // Pages are only served on their own domain and sitemaps must not list URLs of other hosts
        if( config( 'cms.multidomain' ) ) {
            $query->where( ( new Nav() )->qualifyColumn( 'domain' ), (string) request()->route( 'domain', '' ) );
        }

        return $query->toBase();
    }


    /**
     * Returns the absolute CMS page route with row placeholders.
     */
    protected function template() : string
    {
        $params = config( 'cms.multidomain' ) ? ['domain' => '__CMS_DOMAIN__'] : [];

        return route( 'cms.page', $params + ['path' => '__CMS_PATH__'] );
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
        $template = $this->template();

        $query = $this->query()->select( 'path', 'domain', 'updated_at', 'meta' );

        if( $limit !== null ) {
            $query->orderBy( 'id' )->offset( (int) $offset )->limit( $limit );
        }

        return response()->stream( function() use ( $tz, $template, $query ) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $i = 0;
            foreach( $query->cursor() as $page )
            {
                if( $this->noindex( $page->meta ) ) {
                    continue;
                }

                $lastmod = $page->updated_at
                    ? ( new \DateTimeImmutable( $page->updated_at, $tz ) )->format( \DateTimeInterface::ATOM )
                    : '';

                echo '<url>';
                echo '<loc><![CDATA[' . $this->location( $page, $template ) . ']]></loc>';
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
     * Escapes a value for XML text content.
     */
    protected function xml( mixed $value ) : string
    {
        return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
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
            $route = cmsroute( 'cms.sitemap.chunk', ['page' => $n] );
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
