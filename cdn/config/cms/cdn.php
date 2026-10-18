<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL
    |--------------------------------------------------------------------------
    |
    | Scheme and host the pages and files are served from by the CDN, e.g.
    | "https://www.example.com". Leave empty to use the application URL.
    |
    */
    'url' => env( 'CMS_CDN_URL' ),

    /*
    |--------------------------------------------------------------------------
    | Delay
    |--------------------------------------------------------------------------
    |
    | Seconds to wait before changed URLs are purged, e.g. until replicas of
    | the database are up to date. Use 0 to purge each change immediately.
    | The sync queue purges the URLs after the response is sent and doesn't
    | retry failed purges.
    |
    */
    'delay' => (int) env( 'CMS_CDN_DELAY', 0 ),

    /*
    |--------------------------------------------------------------------------
    | Limit
    |--------------------------------------------------------------------------
    |
    | Cloudflare, Fastly and Symfony HttpCache remove all content with one
    | request instead if more URLs are purged at once, which is faster and saves API requests.
    | This also removes the cached files and assets, which are then fetched
    | from the server again. Use 0 to always purge the URLs.
    |
    */
    'limit' => (int) env( 'CMS_CDN_LIMIT', 500 ),

    /*
    |--------------------------------------------------------------------------
    | Cache lifetime
    |--------------------------------------------------------------------------
    |
    | Seconds the CDN caches public pages, which replaces the cache time set
    | for each page, and seconds the CDN serves outdated pages while fetching
    | the new version or if the server is unavailable. Leave empty to use the
    | cache time of the pages without serving outdated content.
    |
    */
    'maxage' => env( 'CMS_CDN_MAXAGE' ),
    'stale' => (int) env( 'CMS_CDN_STALE', 0 ),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for the response of the CDN API or caching proxy.
    |
    */
    'timeout' => (int) env( 'CMS_CDN_TIMEOUT', 10 ),

    /*
    |--------------------------------------------------------------------------
    | CDN clients
    |--------------------------------------------------------------------------
    |
    | Changed pages and removed files are purged from all configured clients.
    | Clients which are NULL are skipped. Drivers are the proxy clients of
    | FOSHttpCache: "cloudflare", "fastly", "nginx", "symfony" and "varnish".
    | "servers" and "base_uri" configure the HTTP dispatcher and "options"
    | are passed to the proxy client, see https://foshttpcache.readthedocs.io
    |
    | The optional "hosts" list limits a client to URLs of these host names,
    | e.g. to use one Cloudflare zone per domain in multi-domain setups.
    |
    | The optional "queries" setting bans the URLs including all their query
    | strings instead of purging them, which requires a driver supporting
    | bans like "varnish" and ban support in its configuration.
    |
    | The optional "refresh" setting fetches the URLs again instead of only
    | removing them, which requires a driver supporting refreshes like
    | "fastly", "nginx", "symfony" or "varnish".
    |
    | The optional "after" setting names the client caching the responses
    | of this one, e.g. 'after' => 'varnish' for a CDN in front of Varnish.
    | The client is purged after the inner client succeeded, so it can't
    | fetch outdated content from the inner cache again.
    |
    | The optional "delay" replaces the global delay for the client.
    |
    */
    'clients' => [
        'cloudflare' => env( 'CMS_CDN_CLOUDFLARE_TOKEN' ) ? [
            'driver' => 'cloudflare',
            'servers' => ['https://api.cloudflare.com'],
            'options' => [
                'authentication_token' => env( 'CMS_CDN_CLOUDFLARE_TOKEN' ), // requires "Zone.Cache Purge" permission
                'zone_identifier' => env( 'CMS_CDN_CLOUDFLARE_ZONE' ),
            ],
        ] : null,
        'fastly' => env( 'CMS_CDN_FASTLY_TOKEN' ) ? [
            'driver' => 'fastly',
            'servers' => ['https://api.fastly.com'],
            'options' => [
                'authentication_token' => env( 'CMS_CDN_FASTLY_TOKEN' ),
                'service_identifier' => env( 'CMS_CDN_FASTLY_SERVICE' ),
                'soft_purge' => (bool) env( 'CMS_CDN_FASTLY_SOFT', true ), // mark as stale instead of removing
            ],
        ] : null,
        'varnish' => env( 'CMS_CDN_VARNISH_SERVERS' ) ? [
            'driver' => 'varnish',
            'servers' => explode( ',', (string) env( 'CMS_CDN_VARNISH_SERVERS' ) ), // e.g. "10.0.0.1:6081,10.0.0.2:6081"
        ] : null,
    ],
];
