<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\PurgeCdn;
use FOS\HttpCache\CacheInvalidator;
use FOS\HttpCache\ProxyClient\HttpDispatcher;
use FOS\HttpCache\ProxyClient\HttpProxyClient;
use FOS\HttpCache\ProxyClient\Invalidation\ClearCapable;
use FOS\HttpCache\ProxyClient\Invalidation\PurgeCapable;
use Http\Adapter\Guzzle7\Client;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Illuminate\Support\Facades\Bus;


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
     * Returns the FOSHttpCache proxy client class of the configured driver.
     *
     * @param array<string, mixed> $config Client configuration
     * @return class-string<HttpProxyClient&PurgeCapable> Proxy client class
     * @throws \InvalidArgumentException If the driver isn't a FOSHttpCache proxy client which can purge URLs
     */
    public static function driver( array $config ) : string
    {
        // Drivers are the HTTP proxy clients of FOSHttpCache, so configurations can't create other classes
        $driver = (string) ( $config['driver'] ?? '' );
        $class = 'FOS\\HttpCache\\ProxyClient\\' . ucfirst( $driver );

        if( !ctype_alpha( $driver ) || !is_subclass_of( $class, HttpProxyClient::class ) || !is_subclass_of( $class, PurgeCapable::class ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown CDN driver "%s"', $driver ) );
        }

        return $class;
    }


    /**
     * Returns the absolute URL of a path on the public disk of the CMS files.
     *
     * @param string $path Relative file path
     */
    public static function file( string $path ) : string
    {
        $url = FileStorage::disk( 'public' )->url( $path );

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
        $class = self::driver( $config );

        $dispatcher = new HttpDispatcher( $servers, (string) ( $config['base_uri'] ?? '' ), $http );
        $client = new $class( $dispatcher, (array) ( $config['options'] ?? [] ) );

        return new CacheInvalidator( $client );
    }


    /**
     * Queues one purge job per client, server and chunk of URLs.
     *
     * Outer caches configured with "after" are purged in a chain after the inner caches they fetch the content from
     * succeeded, so they can't fetch outdated content again. Using the sync queue, the URLs are purged after the
     * response is sent in the same order and aren't retried.
     *
     * @param list<string> $urls Absolute URLs
     */
    public static function purge( array $urls ) : void
    {
        $urls = array_values( array_unique( array_filter( $urls ) ) );
        $limit = max( 0, (int) config( 'cms.cdn.limit', 0 ) );
        $conn = config( 'cms.queue.connection' ) ?: config( 'queue.default' );
        $sync = config( 'queue.connections.' . $conn . '.driver' ) === 'sync';
        $clients = self::clients();
        $stacks = self::stacks( $clients );
        $sizes = array_count_values( $stacks );
        $chains = [];

        foreach( $stacks as $name => $root )
        {
            if( !$list = self::urls( $config = $clients[$name], $urls ) ) {
                continue;
            }

            // Only some clients like Cloudflare and Fastly can remove all content with one request
            if( $limit > 0 && count( $list ) > $limit && is_subclass_of( self::driver( $config ), ClearCapable::class ) ) {
                $list = [self::ALL];
            }

            $delay = max( 0, (int) ( $config['delay'] ?? config( 'cms.cdn.delay', 0 ) ) );

            foreach( array_chunk( $list, self::CHUNK ) as $chunk )
            {
                foreach( self::servers( $config ) as $server )
                {
                    $job = new PurgeCdn( $name, $chunk, $server );

                    if( $sync ) {
                        // The sync queue can't delay jobs, so the URLs are purged after the response is sent instead
                        dispatch( $job )->afterResponse();
                    } elseif( $sizes[$root] > 1 ) {
                        $chains[$root][] = $job->delay( $delay ?: null );
                    } else {
                        dispatch( $job->delay( $delay ?: null ) );
                    }
                }
            }
        }

        foreach( $chains as $jobs ) {
            Bus::chain( $jobs )->dispatch();
        }
    }


    /**
     * Returns the servers which get their own jobs, so a failing server doesn't repeat the purges of the others.
     *
     * @param array<string, mixed> $config Client configuration
     * @return list<string|null> Server addresses or only NULL for all servers
     */
    public static function servers( array $config ) : array
    {
        $servers = array_values( array_unique( array_map( strval( ... ), (array) ( $config['servers'] ?? [] ) ) ) );

        return count( $servers ) > 1 ? $servers : [null];
    }


    /**
     * Returns the client names ordered by their position in the cache stack with the name of the innermost cache.
     *
     * @param array<string, array<string, mixed>> $clients Client name => client configuration
     * @return array<string, string> Client name => name of the innermost client it fetches the content from
     */
    public static function stacks( array $clients ) : array
    {
        $result = $depths = [];

        foreach( $clients as $name => $config )
        {
            $root = $name;
            $depth = 0;

            // Unknown or unconfigured inner clients are skipped and cycles end after visiting each client once
            while( is_string( $after = $clients[$root]['after'] ?? null ) && isset( $clients[$after] ) && $depth < count( $clients ) )
            {
                $root = $after;
                $depth++;
            }

            $result[$name] = $root;
            $depths[$name] = $depth;
        }

        uksort( $result, fn( $a, $b ) => $depths[$a] <=> $depths[$b] );
        return $result;
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
}
