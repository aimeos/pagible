<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\PurgeCdn;
use Aimeos\Cms\Models\File;
use FOS\HttpCache\CacheInvalidator;
use FOS\HttpCache\ProxyClient\Cloudflare;
use FOS\HttpCache\ProxyClient\Fastly;
use FOS\HttpCache\ProxyClient\HttpDispatcher;
use FOS\HttpCache\ProxyClient\Varnish;
use Http\Adapter\Guzzle7\Client;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Illuminate\Support\Facades\Storage;


/**
 * Creates the FOSHttpCache invalidators for the configured CDN clients and queues the purges.
 */
class Cdn
{
    /** Placeholder URL for removing all content from the cache */
    public const ALL = '*';

    /** URLs per queued job */
    public const CHUNK = 250;

    /**
     * Returns the scheme and host the pages and files are served from by the CDN.
     */
    public static function base() : string
    {
        return rtrim( (string) ( config( 'cms.cdn.url' ) ?: config( 'app.url' ) ), '/' );
    }


    /**
     * Returns the configured clients, which are NULL if their credentials or servers aren't set.
     *
     * @return array<string, array<string, mixed>> Client name => client configuration
     */
    public static function clients() : array
    {
        return array_filter( (array) config( 'cms.cdn.clients', [] ), is_array( ... ) );
    }


    /**
     * Returns the absolute URL of a path on the public disk of the CMS files.
     *
     * @param string $path Relative file path
     */
    public static function file( string $path ) : string
    {
        $url = Storage::disk( File::diskName( 'public' ) )->url( $path );

        // Local disks without an "url" setting return URLs relative to the application URL
        return str_starts_with( $url, 'http' ) ? $url : self::base() . '/' . ltrim( $url, '/' );
    }


    /**
     * Returns the cache invalidator for the given client configuration.
     *
     * @param array<string, mixed> $config Client configuration
     * @param HttpAsyncClient|null $http HTTP client, NULL for the "cms.cdn.http" container binding or the default one
     */
    public static function invalidator( array $config, ?HttpAsyncClient $http = null ) : CacheInvalidator
    {
        $http ??= app()->bound( 'cms.cdn.http' ) ? app( 'cms.cdn.http' ) : self::http();
        $servers = array_values( array_map( strval( ... ), (array) ( $config['servers'] ?? [] ) ) );

        $client = match( $config['driver'] ?? null ) {
            'cloudflare' => new Cloudflare( new HttpDispatcher( ['https://api.cloudflare.com'], '', $http ), [
                'authentication_token' => (string) $config['token'],
                'zone_identifier' => (string) $config['zone'],
            ] ),
            'fastly' => new Fastly( new HttpDispatcher( ['https://api.fastly.com'], '', $http ), [
                'authentication_token' => (string) $config['token'],
                'service_identifier' => (string) $config['service'],
                'soft_purge' => (bool) ( $config['soft'] ?? true ),
            ] ),
            'varnish' => new Varnish( new HttpDispatcher( $servers, '', $http ) ),
            default => throw new \InvalidArgumentException( sprintf( 'Unknown CDN driver "%s"', $config['driver'] ?? '' ) ),
        };

        return new CacheInvalidator( $client );
    }


    /**
     * Queues one purge job per client and chunk of URLs.
     *
     * Outer caches can wait longer than the caches they fetch the content from, so the inner caches are purged first.
     * Using the sync queue, the URLs are purged after the response is sent and aren't retried.
     *
     * @param list<string> $urls Absolute URLs
     */
    public static function purge( array $urls ) : void
    {
        $urls = array_values( array_unique( array_filter( $urls ) ) );
        $limit = max( 0, (int) config( 'cms.cdn.limit', 0 ) );

        foreach( self::clients() as $name => $config )
        {
            if( !$list = self::urls( $config, $urls ) ) {
                continue;
            }

            // Only Cloudflare and Fastly can remove all content with one request
            if( $limit > 0 && count( $list ) > $limit && self::invalidator( $config )->supports( CacheInvalidator::CLEAR ) ) {
                $list = [self::ALL];
            }

            $delay = max( 0, (int) ( $config['delay'] ?? config( 'cms.cdn.delay', 0 ) ) );

            foreach( array_chunk( $list, self::CHUNK ) as $chunk )
            {
                $job = PurgeCdn::dispatch( $name, $chunk );
                // The sync queue can't delay jobs, so the URLs are purged after the response is sent instead
                self::sync() ? $job->afterResponse() : $job->delay( $delay ?: null );
            }
        }
    }


    /**
     * Returns the URLs which belong to the hosts the client is limited to.
     *
     * @param array<string, mixed> $config Client configuration
     * @param list<string> $urls Absolute URLs
     * @return list<string> URLs purged by the client
     */
    public static function urls( array $config, array $urls ) : array
    {
        if( !$hosts = (array) ( $config['hosts'] ?? [] ) ) {
            return $urls;
        }

        return array_values( array_filter( $urls, fn( string $url ) =>
            $url === self::ALL || in_array( parse_url( $url, PHP_URL_HOST ), $hosts, true )
        ) );
    }


    /**
     * Returns the HTTP client with a response timeout that doesn't follow redirects.
     */
    private static function http() : HttpAsyncClient
    {
        $timeout = max( 1, (int) config( 'cms.cdn.timeout', 10 ) );

        return new PluginClient( Client::createWithConfig( [
            'allow_redirects' => false,
            'connect_timeout' => min( 5, $timeout ),
            'http_errors' => false,
            'timeout' => $timeout,
        ] ), [new ErrorPlugin()] );
    }


    /**
     * Tests if the CDN jobs run synchronously, which can't delay them.
     */
    private static function sync() : bool
    {
        $conn = config( 'cms.queue.connection' ) ?: config( 'queue.default' );
        return config( 'queue.connections.' . $conn . '.driver' ) === 'sync';
    }
}
