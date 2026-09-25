# Pagible CDN

Purges changed pages and removed files of [Pagible CMS](https://pagible.com) from CDNs and caching
proxies using [FOSHttpCache](https://foshttpcache.readthedocs.io). Supported are Cloudflare, Fastly
and Varnish.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

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

Further settings:

| Key | Default | Description |
|-----|---------|-------------|
| `url` | `APP_URL` | Scheme and host the CDN serves the pages and files from (`CMS_CDN_URL`) |
| `delay` | `0` | Seconds to wait before purging changed URLs, can be set per client too (`CMS_CDN_DELAY`) |
| `limit` | `500` | More URLs at once remove all content from Cloudflare and Fastly with one request instead, including files and assets, `0` disables it (`CMS_CDN_LIMIT`) |
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

Varnish must be configured to accept `PURGE` requests from the application servers, see the
[FOSHttpCache proxy configuration](https://foshttpcache.readthedocs.io/en/latest/proxy-configuration.html).

### Stacked caches

If a CDN caches the responses of Varnish, the inner cache must be purged first, otherwise
the CDN fetches the outdated page from the inner cache again. Set a longer `delay` for the CDN client,
e.g. `'delay' => 10`. `cms:cdn:purge` purges the clients in the
configured order, so list the inner cache first. If the purge of the inner cache fails, the CDN may
cache the outdated page again until its cache lifetime expires.

### Several zones or services

Add a client per zone or service and limit it to its host names with `hosts`, e.g. for multi-domain
setups with one Cloudflare zone per domain:

```php
'clients' => [
    'shop' => [
        'driver' => 'cloudflare',
        'token' => env( 'CMS_CDN_SHOP_TOKEN' ),
        'zone' => env( 'CMS_CDN_SHOP_ZONE' ),
        'hosts' => ['shop.example.com'],
    ],
    'blog' => [
        'driver' => 'cloudflare',
        'token' => env( 'CMS_CDN_BLOG_TOKEN' ),
        'zone' => env( 'CMS_CDN_BLOG_ZONE' ),
        'hosts' => ['blog.example.com'],
    ],
],
```

## What is purged

* **Pages**: The URLs of pages whose published content, route, access rules or files changed, i.e.
  the same pages the complete-page cache of the theme package invalidates. A moved page is purged at
  its old and new URL. The URLs are generated from the `cms.page` route and `CMS_CDN_URL` or `APP_URL`, or the page
  domain if `cms.multidomain` is enabled. Without the theme package, no page URLs are purged.
* **Files**: The public URLs of files and previews deleted from storage or moved to the private disk.
  New uploads always get new file names, so their URLs never serve outdated content.
* **Shared content**: All pages which use a published, deleted or restored shared element or file.
  The pages are found by queued jobs on the core queue (`CMS_QUEUE_CONNECTION`, `CMS_QUEUE`), also if
  the items are finally removed. Pages using deleted
  items were purged when the items were deleted, so finally removing them doesn't purge them again.

Not purged are:

* URLs with query strings, e.g. search or pagination parameters. The CDN caches each query string
  separately, so configure it to ignore query strings for pages that don't use them, e.g. with a
  Cloudflare cache rule, or keep the cache lifetime of these responses short
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
```

Removing all content is only supported by Cloudflare and Fastly.

Equal purges which are still queued aren't queued again, so large changes of shared content remove all
content only once instead of once per batch of pages.

Removing all content also removes the cached images, CSS and JavaScript files, which are then fetched
from your server again. If shared elements or files like the footer are used by more pages than the
`limit`, each of their changes does this, so raise the limit or use `0` for large sites with frequent
changes of shared content.

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
Each client uses its own jobs, so a failing CDN doesn't delay the others. The job only contains the client name and URLs; credentials
are read from the configuration when the job runs, so changed credentials apply to queued purges too
and removed clients don't receive them any more.

With the `sync` queue, the URLs are purged after the response is sent to keep the admin panel fast.
Failed purges are only reported then and not retried, so use a real queue in production.
