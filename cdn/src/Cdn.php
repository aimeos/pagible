<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\FlushCdn;
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
use Illuminate\Cache\NullStore;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;


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
     * Returns the absolute URL, URLs without a scheme are relative to the CDN or application URL.
     *
     * @param string $url Absolute URL or path
     */
    public static function absolute( string $url ) : string
    {
        return preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ? $url : self::base() . '/' . ltrim( $url, '/' );
    }


    /**
     * Returns the scheme and host the pages and files are served from by the CDN.
     */
    public static function base() : string
    {
        return rtrim( (string) ( config( 'cms.cdn.url' ) ?: config( 'app.url' ) ), '/' );
    }


    /**
     * Returns the configured clients, skipping the ones without credentials or servers.
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
        // Local disks without an "url" setting return URLs relative to the application URL
        return self::absolute( FileStorage::disk( 'public' )->url( $path ) );
    }


    /**
     * Returns the cache invalidator for the given client configuration.
     *
     * The HTTP client can be replaced by binding a HttpAsyncClient as "cms.cdn.http" in the container.
     *
     * @param array<string, mixed> $config Client configuration
     */
    public static function invalidator( array $config ) : CacheInvalidator
    {
        $http = app()->bound( 'cms.cdn.http' ) ? app( 'cms.cdn.http' ) : self::http();
        $class = self::driver( $config );

        $dispatcher = new HttpDispatcher( self::servers( $config ), (string) ( $config['base_uri'] ?? '' ), $http );
        $client = new $class( $dispatcher, (array) ( $config['options'] ?? [] ) );

        return new CacheInvalidator( $client );
    }


    /**
     * Returns the configured client the client fetches its content from.
     *
     * @param array<string, mixed> $config Client configuration
     * @param array<string, array<string, mixed>> $clients Client name => client configuration
     * @return string|null Name of the inner client, NULL if none or unknown
     */
    public static function inner( array $config, array $clients ) : ?string
    {
        return is_string( $after = $config['after'] ?? null ) && isset( $clients[$after] ) ? $after : null;
    }


    /**
     * Returns the absolute URL of a page served by the CDN.
     *
     * @param string $path Page path
     * @param string $domain Domain of the page in multi-domain setups, empty for the domain of the CDN URL
     */
    public static function page( string $path, string $domain = '' ) : string
    {
        $base = self::base();
        $params = [];

        // Routes are relative to the configured URL, not to the host of the current request
        if( config( 'cms.multidomain' ) )
        {
            $domain = $domain ?: (string) parse_url( $base, PHP_URL_HOST );
            $base = ( parse_url( $base, PHP_URL_SCHEME ) ?: 'https' ) . '://' . $domain;
            $params = ['domain' => $domain];
        }

        return $base . URL::route( 'cms.page', ['path' => $path] + $params, false );
    }


    /**
     * Purges the URLs from all clients.
     *
     * With a global delay, the URLs changed during the delay are collected and purged together, which saves API
     * requests if editors publish several changes in a row. Using the sync queue, the URLs are purged after the
     * response is sent and aren't retried.
     *
     * @param list<string> $urls Absolute URLs
     */
    public static function purge( array $urls ) : void
    {
        if( !$urls = array_values( array_unique( array_filter( $urls ) ) ) ) {
            return;
        }

        $delay = max( 0, (int) config( 'cms.cdn.delay', 0 ) );
        $tenant = Tenancy::value();
        $batch = null;

        // The null cache store can't keep the URLs, so they are purged on their own
        if( $delay > 0 && !self::sync() && !Cache::getStore() instanceof NullStore )
        {
            try {
                $batch = CdnBatch::add( $tenant, $urls, $delay );
            } catch( \Throwable $e ) {
                // Without a shared cache store supporting locks, each change is purged on its own
                report( $e );
            }
        }

        if( !$batch ) {
            self::queue( $urls );
            return;
        }

        if( $batch['new'] ) {
            dispatch( ( new FlushCdn( $tenant ) )->delay( $delay ) );
        }

        // Queueing many jobs can take longer than the lock lasts, so rejected URLs are queued afterwards
        if( $batch['overflow'] ) {
            self::queue( $batch['overflow'] );
        }
    }


    /**
     * Queues one purge job per client, server and chunk of URLs.
     *
     * Outer caches configured with "after" are purged in a chain after the inner caches they fetch the content from
     * succeeded, so they can't fetch outdated content again.
     *
     * @param list<string> $urls Absolute URLs
     * @param int $elapsed Seconds the URLs already waited, which are subtracted from the delays of the clients
     * @param string|null $tenant ID of the tenant the URLs belong to, NULL for the current tenant
     */
    public static function queue( array $urls, int $elapsed = 0, ?string $tenant = null ) : void
    {
        $tenant ??= Tenancy::value();
        $limit = max( 0, (int) config( 'cms.cdn.limit', 0 ) );
        $sync = self::sync();
        $clients = self::clients();
        $stacks = self::stacks( $clients );
        $sizes = array_count_values( $stacks );
        $chains = [];

        foreach( $stacks as $name => $root )
        {
            if( !$list = self::list( $config = $clients[$name], $urls, $limit ) ) {
                continue;
            }

            $delay = max( 0, (int) ( $config['delay'] ?? config( 'cms.cdn.delay', 0 ) ) - $elapsed );
            $servers = self::servers( $config );

            foreach( array_chunk( $list, self::CHUNK ) as $chunk )
            {
                foreach( count( $servers ) > 1 ? $servers : [null] as $server )
                {
                    $job = new PurgeCdn( $name, $chunk, $server, $tenant );

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
     * Returns the unique server addresses of the client, clients with several servers get one job per server.
     *
     * @param array<string, mixed> $config Client configuration
     * @return list<string> Server addresses
     */
    public static function servers( array $config ) : array
    {
        $servers = array_map( fn( $server ) => trim( (string) $server ), (array) ( $config['servers'] ?? [] ) );

        return array_values( array_unique( array_filter( $servers, fn( $server ) => $server !== '' ) ) );
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
            while( ( $inner = self::inner( $clients[$root], $clients ) ) !== null && $depth < count( $clients ) )
            {
                $root = $inner;
                $depth++;
            }

            $result[$name] = $root;
            $depths[$name] = $depth;
        }

        uksort( $result, fn( $a, $b ) => $depths[$a] <=> $depths[$b] );
        return $result;
    }


    /**
     * Returns whether the client uses the enabled "queries" and "refresh" options, which depends on the driver.
     *
     * @param array<string, mixed> $config Client configuration
     * @return array<string, bool> Name of the enabled option => TRUE if the driver supports it
     */
    public static function supports( array $config, CacheInvalidator $invalidator ) : array
    {
        $options = ['queries' => CacheInvalidator::INVALIDATE, 'refresh' => CacheInvalidator::REFRESH];
        $result = [];

        foreach( $options as $name => $operation )
        {
            if( !empty( $config[$name] ) ) {
                $result[$name] = $invalidator->supports( $operation );
            }
        }

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


    /**
     * Returns the URLs purged by the client or only Cdn::ALL if removing all content is allowed and faster.
     *
     * @param array<string, mixed> $config Client configuration
     * @param list<string> $urls Absolute URLs
     * @param int $limit Number of URLs above which all content is removed, 0 to always purge the URLs
     * @return list<string> URLs purged by the client
     */
    private static function list( array $config, array $urls, int $limit ) : array
    {
        $list = self::urls( $config, $urls );

        // Only some clients like Cloudflare and Fastly can remove all content with one request. In multi-tenant
        // setups, this would remove the content of all tenants sharing the zone or service too
        if( $limit > 0 && count( $list ) > $limit && (bool) ( $config['clear'] ?? !Tenancy::configured() )
            && is_subclass_of( self::driver( $config ), ClearCapable::class )
        ) {
            return [self::ALL];
        }

        return $list;
    }


    /**
     * Returns whether the CMS jobs use the sync queue, which can't delay jobs.
     */
    private static function sync() : bool
    {
        return config( 'queue.connections.' . Utils::queue() . '.driver' ) === 'sync';
    }
}
