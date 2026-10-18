# Pagible CDN

Purges changed pages and removed files of [Pagible CMS](https://pagible.com) from CDNs and caching
proxies using [FOSHttpCache](https://foshttpcache.readthedocs.io). Supported are all its proxy clients:
Cloudflare, Fastly, Nginx, Symfony HttpCache and Varnish.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [What is purged](#what-is-purged)
- [Commands](#commands)
- [Other CDNs and URLs](#other-cdns-and-urls)
- [Protected content](#protected-content)
- [Failures](#failures)
- [Metrics](#metrics)

## Installation

```bash
composer require aimeos/pagible-cdn
php artisan vendor:publish --provider="Aimeos\Cms\CdnServiceProvider"
```

Purge requests are sent by queued jobs on the CMS queue (`CMS_QUEUE_CONNECTION`, `CMS_QUEUE`), so run a
queue worker:

```bash
php artisan queue:work
```

## Configuration

Settings are in `config/cms/cdn.php`. Each client is used as soon as its token or servers are set,
all others are skipped:

| Driver | Environment variables |
|--------|-----------------------|
| `cloudflare` | `CMS_CDN_CLOUDFLARE_TOKEN` (API token with the "Zone.Cache Purge" permission), `CMS_CDN_CLOUDFLARE_ZONE` |
| `fastly` | `CMS_CDN_FASTLY_TOKEN`, `CMS_CDN_FASTLY_SERVICE`, `CMS_CDN_FASTLY_SOFT` (default `true`, marks content as stale) |
| `varnish` | `CMS_CDN_VARNISH_SERVERS`, comma separated, e.g. `10.0.0.1:6081,10.0.0.2:6081` |

The `driver` is the name of the [FOSHttpCache proxy client](https://foshttpcache.readthedocs.io/en/latest/proxy-clients.html):
`cloudflare`, `fastly`, `nginx`, `symfony` or `varnish`. `servers` and the optional `base_uri` configure its
HTTP dispatcher and `options` are passed to the proxy client unchanged, using the option names of
FOSHttpCache. Clients for Nginx and Symfony HttpCache are added to the `clients` array, e.g.:

```php
'nginx' => [
    'driver' => 'nginx',
    'servers' => ['10.0.0.1:8080'],
    'options' => ['purge_location' => '/purge'],
],
```

Run `php artisan cms:cdn:check` after deploying to check the configuration of all clients without
sending requests. With `--send`, it also purges the test URL `/cms-cdn-check` of each client, which
shows if the credentials and servers work.

Since 0.14, the Cloudflare and Fastly settings use the option names of FOSHttpCache. If you've published
`config/cms/cdn.php` before, publish it again or rename `token` to `options.authentication_token`, `zone` to
`options.zone_identifier`, `service` to `options.service_identifier` and `soft` to `options.soft_purge`, and add
the API URL as `servers` (`https://api.cloudflare.com` or `https://api.fastly.com`).

Further settings:

| Key | Default | Description |
|-----|---------|-------------|
| `url` | `APP_URL` | Scheme and host the CDN serves the pages and files from (`CMS_CDN_URL`) |
| `delay` | `0` | Seconds to wait before purging changed URLs, can be set per client too. URLs changed meanwhile are purged together (`CMS_CDN_DELAY`) |
| `limit` | `500` | More URLs at once remove all content from Cloudflare, Fastly and Symfony HttpCache with one request instead, including files and assets, `0` disables it (`CMS_CDN_LIMIT`) |
| `maxage` | page setting | Seconds the CDN caches public pages (`CMS_CDN_MAXAGE`) |
| `stale` | `0` | Seconds the CDN serves outdated pages while refreshing them or if the server fails (`CMS_CDN_STALE`) |
| `timeout` | `10` | Seconds to wait for the response (`CMS_CDN_TIMEOUT`) |

### Cache lifetime

Public pages are sent with `Cache-Control: public, s-maxage=...` using the cache time of each page,
while pages with access rules and pages viewed by editors are private. As changed pages are purged,
the CDN can keep pages longer, e.g. `CMS_CDN_MAXAGE=86400`. `CMS_CDN_STALE=60` adds
`stale-while-revalidate` and `stale-if-error`, so the CDN serves the old page while fetching the new
one or if your server is unavailable. If `CMS_CDN_MAXAGE` is set, the `Expires` header is removed so
the CDN can't use the shorter lifetime of the page instead.

Varnish, Nginx and Symfony HttpCache must be configured to accept `PURGE` requests from the application servers, see the
[FOSHttpCache proxy configuration](https://foshttpcache.readthedocs.io/en/latest/proxy-configuration.html).

### Stacked caches

If a CDN caches the responses of Varnish, the inner cache must be purged first, otherwise
the CDN fetches the outdated page from the inner cache again. Add `'after' => 'varnish'` to the CDN
client, so it's purged after the purge of Varnish succeeded, also by `cms:cdn:purge`. Several clients
can be stacked this way. If the purge of the inner cache fails after all retries, the outer clients
aren't purged at all, because they would fetch the outdated page again anyway.

`'refresh' => true` fetches the purged URLs again instead of only removing them, so the next visitor
doesn't wait for the page to be rendered. This requires a driver supporting refreshes (Fastly, Nginx,
Symfony HttpCache and Varnish), for Varnish together with `queries`, the URLs are banned and refreshed.

### Batched purges

With a global `delay`, e.g. `CMS_CDN_DELAY=10`, the URLs changed during the delay are collected and purged
together after it, so publishing several changes in a row only sends one request per client instead of
one per change. This saves API requests counting against the rate limit of the CDN. Clients with a longer
`delay` of their own wait for the rest of their delay afterwards.

The URLs are collected in the default cache store, so it must be shared by the application servers and
queue workers and support locks, like Redis, Memcached or the database store. With the `null` cache
store, the `sync` queue or a delay of `0`, each change is purged on its own. Batches with more than 2000
URLs or rejected by the cache store are purged at once, so the cache entry stays small.

### Several zones or services

Add a client per zone or service and limit it to its host names with `hosts`, e.g. for multi-domain
setups with one Cloudflare zone per domain:

```php
'clients' => [
    'shop' => [
        'driver' => 'cloudflare',
        'servers' => ['https://api.cloudflare.com'],
        'options' => [
            'authentication_token' => env( 'CMS_CDN_SHOP_TOKEN' ),
            'zone_identifier' => env( 'CMS_CDN_SHOP_ZONE' ),
        ],
        'hosts' => ['shop.example.com'],
    ],
    'blog' => [
        'driver' => 'cloudflare',
        'servers' => ['https://api.cloudflare.com'],
        'options' => [
            'authentication_token' => env( 'CMS_CDN_BLOG_TOKEN' ),
            'zone_identifier' => env( 'CMS_CDN_BLOG_ZONE' ),
        ],
        'hosts' => ['blog.example.com'],
    ],
],
```

### Removing all content

Only Cloudflare, Fastly and Symfony HttpCache support removing all content, see `limit` above.

Equal purges which are still queued aren't queued again, so large changes of shared content remove all
content only once instead of once per batch of pages.

Removing all content also removes the cached images, CSS and JavaScript files, which are then fetched
from your server again. If shared elements or files like the footer are used by more pages than the
`limit`, each of their changes does this, so raise the limit or use `0` for large sites with frequent
changes of shared content.

In multi-tenant setups, the content isn't removed at once by default, because this also removes the
content of the other tenants sharing the CDN zone or service. Add `'clear' => true` to clients which
only serve one tenant, e.g. using `hosts`. `cms:cdn:purge --all` always removes all content.

## What is purged

* **Pages**: The same pages the complete-page cache of the theme package invalidates. Pages are purged when
  * a new version is published, also by scheduled publishing
  * the URL path or domain changes on publishing; the page is purged at its old and new URL
  * access rules are set or removed
  * the page is deleted or permanently removed, including its subpages
  * files they use are deleted from storage or moved to the private disk
  * editors use the "Clear cache" action

  Moving a page in the page tree doesn't change its URL and doesn't purge it. The URLs are generated from
  the `cms.page` route and `CMS_CDN_URL` or `APP_URL`, or the page domain if `cms.multidomain` is enabled.
  Without the theme package, no page URLs are purged.
* **Files**: The public URLs of files and previews deleted from storage or moved to the private disk.
  New uploads always get new file names, so their URLs never serve outdated content.
* **Shared content**: All pages which use a published, deleted or restored shared element or file.
  The pages are found by queued jobs on the core queue (`CMS_QUEUE_CONNECTION`, `CMS_QUEUE`), also if
  the items are finally removed. Pages using deleted
  items were purged when the items were deleted, so finally removing them doesn't purge them again.

Not purged are:

* URLs with query strings, e.g. search or pagination parameters. The CDN caches each query string
  separately, so configure it to ignore query strings for pages that don't use them, e.g. with a
  Cloudflare cache rule, or keep the cache lifetime of these responses short. For Varnish, add
  `'queries' => true` to the client to ban the URLs including all their query strings instead, which
  requires [ban support](https://foshttpcache.readthedocs.io/en/latest/varnish-configuration.html)
  in your VCL
* sitemaps, which are updated when their CDN cache expires
* other pages which show the changed page, e.g. in the navigation. They are updated when their CDN
  cache expires, so keep the cache lifetime of HTML pages short

Editors can purge a page and all its subpages with the "Clear cache" action of the admin panel. For
the root page, this purges the whole site and large sites remove all content at once.

## Commands

```bash
php artisan cms:cdn:purge https://example.com/a /b # purge URLs, paths are relative to CMS_CDN_URL or APP_URL
php artisan cms:cdn:purge --all                    # remove all content, e.g. after theme changes
php artisan cms:cdn:purge --all --client=fastly    # limit to one or more clients
php artisan cms:cdn:check                          # check the client configuration without sending requests
php artisan cms:cdn:check --send                   # also purge a test URL from each client
```

## Other CDNs and URLs

The supported clients are fixed. Proxy clients added to [FOSHttpCache](https://github.com/FriendsOfSymfony/FOSHttpCache) are available as drivers after updating it, so support for another CDN, e.g. Amazon CloudFront, is added there. Until then, listen to the same events of the core package which this package uses, after the database transaction is committed:

```php
use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Events\PageInvalidated;
use Illuminate\Support\Facades\Event;

Event::listen( function( PageInvalidated $event ) {
    // $event->paths: page paths of the $event->domain (empty for pages without domain) and $event->tenant
    // URL::route( 'cms.page', ['path' => $path], false ) returns the relative URL of each path
} );

Event::listen( function( FilesRemoved $event ) {
    // $event->paths: paths of the files on the public disk
} );
```

Queue a job in the listeners, so slow CDN APIs don't delay the editors. To purge URLs of your own routes from the configured clients, e.g. product pages showing CMS content, pass the absolute URLs to `Aimeos\Cms\Cdn::purge()`, which queues the same jobs as the CMS. Bind your own `Http\Client\HttpAsyncClient` as `cms.cdn.http` in the container to send the purge requests through it, e.g. with a proxy or a mock client in tests.

## Protected content

Pages which get access rules and files moved to the private disk are only removed from the CDN by
their purge. Until it succeeds, the CDN still serves the public copy:

* during the `delay` of the client
* until the cache lifetime (`maxage`) expires if the purge fails after all retries or with the `sync`
  queue, and during `stale` seconds while your server fails
* if the `hosts` of the client don't contain the host of the file URLs, e.g. `cdn.example.com`

For sites with protected content, use a real queue, keep `maxage` moderate and add the host of the
public files to `hosts`.

## Failures

Failed purges are retried after 10 seconds, 1, 5 and 15 minutes, also if the CDN API rejects requests
because of its rate limit. Purges which failed after the last retry are stored as failed jobs by Laravel.
Purges of clients with invalid configurations, e.g. missing options, fail at once without retries.
Each client uses its own jobs, so a failing CDN doesn't delay the others, and clients with several
`servers` use one job per server, so only the failed servers are retried. The job only contains the
client name and URLs; credentials are read from the configuration when the job runs, so changed
credentials apply to queued purges too and removed clients or servers don't receive them any more.

Purges which failed after the last retry also log a `cms.cdn.purge_failed` warning with the client,
server and exception class to the `cms.watch.channel` log channel or the default one, at most once
every 10 minutes for the same failure, so you can alert on it without exposing credentials.

With the `sync` queue, the URLs are purged after the response is sent to keep the admin panel fast.
Failed purges are only reported then and not retried, so use a real queue in production.

## Metrics

Each purge request records its client, duration and success as `cdn` observation, which the
`aimeos/pagible-pulse` package shows as "CDN purges" card. Full clears are recorded as
`cdn:clear`, URL purges as `cdn:purge`.
