<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;


class SitemapController extends Controller
{
    /**
     * Maximum number of URLs per sitemap file (per sitemaps.org protocol).
     */
    protected const URLS_PER_SITEMAP = 50000;

    /** @var int Seconds a request waits for the statistics built by another request */
    protected const LOCK_WAIT = 5;

    /**
     * Maximum number of articles per Google News sitemap.
     */
    protected const NEWS_PER_SITEMAP = 1000;

    /**
     * Response headers of all sitemap documents.
     */
    protected const HEADERS = ['Content-Type' => 'application/xml', 'Cache-Control' => 'public, max-age=300'];

    /**
     * Seconds the URL count and the chunk boundaries are cached.
     */
    protected const CACHE_TTL = 300;


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
        $stats = $this->stats();

        if( $stats['count'] <= static::URLS_PER_SITEMAP ) {
            return $this->urlset();
        }

        return $this->sitemapIndex( $stats['count'], $stats['updated'] );
    }


    /**
     * Streams the Google News sitemap for articles published in the last two days.
     *
     * @return StreamedResponse News `<urlset>` XML response
     */
    public function news( string $domain = '' ) : StreamedResponse
    {
        $template = $this->template();
        $tz = $this->timezone();

        $query = $this->query()
            ->where( 'type', 'news' )
            ->where( 'created_at', '>=', now()->subDays( 2 ) )
            ->select( 'path', 'domain', 'lang', 'title', 'created_at', 'meta' )
            ->orderByDesc( 'created_at' )
            ->limit( static::NEWS_PER_SITEMAP );

        return response()->stream( function() use ( $domain, $query, $template, $tz ) {
            $names = [];
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';

            foreach( $query->cursor() as $page )
            {
                if( $this->noindex( $page->meta ) ) {
                    continue;
                }

                // the website title is translated in the root page variant of the article's language
                $name = $names[(string) $page->lang] ??= $this->xml( $this->name( $domain, (string) $page->lang ) );
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
        }, 200, self::HEADERS );
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

        $from = $this->stats()['bounds'][$page] ?? null;

        if( $page > 1 && $from === null ) {
            abort( 404 );
        }

        return $this->urlset( $from, static::URLS_PER_SITEMAP );
    }


    /**
     * Returns the published website title from the root page configuration.
     *
     * @param string $domain Requested domain, empty to match all domains
     * @param string|null $lang Language of the root page variant, NULL for the source variant
     */
    protected function name( string $domain, ?string $lang = null ) : string
    {
        return Nav::rootConfig( $domain, fn( $page ) => trim( (string) ( $page->config->website->data->title ?? '' ) ) ?: null, $lang ) ?? '';
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
        // every published variant has its own URL
        $query = Nav::allVariants()
            ->whereIn( ( new Nav() )->qualifyColumn( 'status' ), [1, 2] )
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
     * Returns the cached URL count, last modification and chunk boundaries of the sitemap.
     *
     * The boundaries are the first variant IDs of the second and following chunks, keyed
     * by the one-based chunk number, so chunks don't have to skip all previous rows.
     *
     * @return array{count: int, updated: string|null, bounds: array<int, string>}
     */
    protected function stats() : array
    {
        $domain = config( 'cms.multidomain' ) ? (string) request()->route( 'domain', '' ) : '';
        $key = 'cms-sitemap:' . Tenancy::value() . ':' . $domain . ':' . static::URLS_PER_SITEMAP;

        if( ( $stats = Cache::get( $key ) ) !== null ) {
            return $stats;
        }

        // only one request builds the stats, concurrent requests wait shortly and use the cached result
        try {
            return $this->rebuild( $key );
        } catch( LockTimeoutException $e ) {
            // workers aren't blocked for long, crawlers retry later
            abort( 503, '', ['Retry-After' => 60] );
        }
    }


    /**
     * Builds and caches the sitemap statistics while holding the lock.
     *
     * @param string $key Cache key of the statistics
     * @return array{count: int, updated: string|null, bounds: array<int, string>}
     * @throws LockTimeoutException If the lock isn't released within a few seconds
     */
    protected function rebuild( string $key ) : array
    {
        return Cache::lock( $key . ':lock', 120 )->block( static::LOCK_WAIT, function() use ( $key ) {

            if( ( $stats = Cache::get( $key ) ) !== null ) {
                return $stats;
            }

            // empty sitemaps aren't cached so requests for arbitrary domains can't fill the cache
            if( ( $stats = $this->build() )['count'] > 0 ) {
                Cache::put( $key, $stats, static::CACHE_TTL );
            }

            return $stats;
        } );
    }


    /**
     * Builds the URL count, last modification and chunk boundaries of the sitemap.
     *
     * All IDs are read once in order instead of skipping the previous rows for each chunk.
     *
     * @return array{count: int, updated: string|null, bounds: array<int, string>}
     */
    protected function build() : array
    {
        $col = ( new Nav() )->qualifyColumn( 'variant_id' );
        $count = 0;
        $updated = null;
        $bounds = [];

        foreach( $this->query()->select( [$col, 'updated_at'] )->lazyById( 10000, $col, 'variant_id' ) as $row )
        {
            if( $count > 0 && $count % static::URLS_PER_SITEMAP === 0 ) {
                $bounds[intdiv( $count, static::URLS_PER_SITEMAP ) + 1] = (string) $row->variant_id;
            }

            if( $updated === null || (string) $row->updated_at > $updated ) {
                $updated = (string) $row->updated_at;
            }

            $count++;
        }

        return ['count' => $count, 'updated' => $updated, 'bounds' => $bounds];
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
     * Returns the application timezone used for sitemap dates.
     */
    protected function timezone() : \DateTimeZone
    {
        return new \DateTimeZone( config( 'app.timezone' ) ?: 'UTC' );
    }


    /**
     * Streams a `<urlset>` XML document.
     *
     * When `$limit` is null all rows are streamed (single-file mode); otherwise
     * the result is sliced via `ORDER BY variant_id LIMIT` starting at the first
     * variant ID of the chunk (keyset pagination) for chunked output.
     * The route URL is resolved once with placeholders and substituted per row
     * to avoid the per-iteration cost of Laravel's URL generator.
     *
     * @param string|null $from First variant ID of the chunk, ignored when `$limit` is null
     * @param int|null $limit  Maximum rows to stream, or null for all rows
     * @return StreamedResponse `<urlset>` XML response
     */
    protected function urlset( ?string $from = null, ?int $limit = null ) : StreamedResponse
    {
        $tz = $this->timezone();
        $template = $this->template();

        $query = $this->query()->select( 'path', 'domain', 'updated_at', 'meta' );

        if( $limit !== null ) {
            $col = ( new Nav() )->qualifyColumn( 'variant_id' );
            $query->when( $from !== null, fn( $q ) => $q->where( $col, '>=', $from ) )->orderBy( $col )->limit( $limit );
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
        }, 200, self::HEADERS );
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
            $tz = $this->timezone();
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
            200, self::HEADERS
        );
    }
}
