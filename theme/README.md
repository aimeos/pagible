# Pagible Theme

Frontend rendering for [Pagible CMS](https://pagible.com). Provides page rendering, search, sitemap, and contact form with Blade templates.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

After installation, the configuration is available in `config/cms/theme.php`:

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `cache` | | `file` (or `array` in debug) | Cache store for rendered pages (from `config/cache.php`) |
| `lock` | `CMS_THEME_LOCK` | `5` | Complete-page render lock lifetime in seconds |
| `stale` | `CMS_THEME_STALE` | `10` | Seconds an expired complete page remains available during revalidation |
| `ttl` | `CMS_THEME_TTL` | `86400` (or `0` in debug) | Time-to-live for cached pages in seconds; `0` disables caching |
| `disk` | `CMS_THEME_DISK` | | Filesystem disk for tenant-uploaded themes; disabled if unconfigured |
| `sitemap` | `CMS_SITEMAP` | `sitemap` | URL path prefix for XML sitemap (`/{sitemap}.xml`) |
| `pageroute` | `CMS_PAGEROUTE` | `{}` | JSON object with catch-all page route options (Laravel route group) |

### Authenticated page caching

Anonymous public pages use complete-response caching owned entirely by the pre-session middleware. It reads cached responses, coordinates rendering, and stores only a final response marked public. Requests carrying the Laravel session cookie or an `Authorization` header bypass that cache and authenticated responses are rendered privately. Applications with other authentication indicators can extend the cheap pre-session check:

The outer `Origin` middleware applies this policy to every theme route and shares its decision with `ServeCachedPage`. Cache admission requires the request scheme and port to match `APP_URL`; in single-domain mode its hostname must match as well. Pagible still passes noncanonical requests to the application, but forces their responses to `private, no-store` and removes `Expires` so neither its complete-page cache, sitemap responses, nor a compliant shared cache stores them. Configure trusted proxies before this middleware so Laravel resolves the public origin correctly. Pagible does not reject unknown hosts; applications should separately enable Laravel's `TrustHosts` middleware or enforce an equivalent host allowlist at the web server, especially when multidomain routing accepts tenant hosts outside `APP_URL`.

In multi-tenant applications, tenant initialization must run before `ServeCachedPage`; otherwise the middleware can read a cache key and query pages without the intended tenant context. Apply the tenancy initializer globally before the CMS routes or add it to the outer `pageroute.middleware` group. Do not place it in `web` or after `ServeCachedPage`. The core package's Stancl tenancy section contains a configuration example.

```php
\Aimeos\Cms\Http\Middleware\ServeCachedPage::bypassUsing(
    fn($request) => $request->hasCookie('sso')
);
```

CDNs must apply the same bypass rules for the session cookie, authorization header, and any custom authentication indicator; otherwise the edge may return public HTML before Laravel receives the request. Multi-node installations must configure a shared lock-capable theme cache store such as Redis so web processes and queued invalidation workers address the same entries.

The built-in session-cookie and `Authorization` checks always remain active. The callback only needs to identify additional authentication mechanisms. Missing pages can still return before the session middleware starts; restricted pages continue through the `web` middleware so Laravel can authenticate the request and handle guest redirects.

### Security headers on cached pages

Complete-page cache entries contain the rendered HTML, not arbitrary response headers from inner middleware. Apply security-header middleware globally or to the outer `pageroute.middleware` group so it decorates cache hits as well as freshly rendered responses:

```php
// config/cms/theme.php
'pageroute' => [
    'middleware' => [
        \App\Http\Middleware\SecurityHeaders::class,
    ],
],
```

Middleware added inside Laravel's `web` group runs only after `ServeCachedPage` and is therefore skipped on cache hits. Origin-wide static headers such as HSTS can alternatively be added by the web server or CDN. Request-specific values fetched by JavaScript from a separate uncached API do not affect page cacheability because they are not embedded in the cached document. Complete-page caching is unsuitable only when a response security header must match a request-specific value embedded in the initial HTML.

### Restricted-page login redirects

Restricted pages throw Laravel's `AuthenticationException` for guests and return `403 Forbidden` for authenticated users without a matching frontend access value. To redirect browser guests to a login page, configure Laravel's standard guest redirect in the host application's `bootstrap/app.php`:

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(
            fn (Request $request) => route('login')
        );
    })
    // ...
    ->create();
```

The named `login` route must be public and registered before the CMS catch-all route. Requests that expect JSON receive `401 Unauthorized` instead of a redirect. Without a configured guest redirect, Laravel returns `401 Unauthorized` for restricted guest requests.

During public-page revalidation, a request that finds another renderer active may receive the previous complete page for `stale` seconds. Without a stale entry, it waits for the render lease, rechecks the cache, and only renders without writing if that bounded wait expires. The cache-store TTL keeps an entry through its stale window, while its fresh expiry remains in the entry. Invalidation deletes entries without waiting for active render leases.

After page publication, deletion, or access changes commit, the theme queues a lightweight job that removes the affected rendered HTML before its normal expiry. Job failures use Laravel's queue retry policy; a queue dispatch failure is reported without undoing the committed content change. The origin cache TTL and CDN `s-maxage` remain the consistency boundary, so stale, restricted, deleted, or moved HTML may remain visible until expiry. Run a queue worker with an asynchronous queue connection in production. Installations using only the core package remain independent of frontend caching.

### Content Security Policy

CSP directives are configured under the `csp` key, with defaults for hCaptcha:

| Option | Env Variable | Default |
|--------|-------------|---------|
| `csp.media-src` | `CMS_CSP_MEDIA_SRC` | |
| `csp.style-src` | `CMS_CSP_STYLE_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |
| `csp.frame-src` | `CMS_CSP_FRAME_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |
| `csp.script-src` | `CMS_CSP_SCRIPT_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |
| `csp.connect-src` | `CMS_CSP_CONNECT_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |

## Commands

### cms:install:theme

Installs the Pagible Theme package.

```bash
php artisan cms:install:theme
```

Publishes theme files and adds hCaptcha configuration to `config/services.php`. Requires `HCAPTCHA_SITEKEY` and `HCAPTCHA_SECRET` environment variables for contact form spam protection.

### cms:benchmark:theme

Runs page rendering and controller benchmarks.

```bash
php artisan cms:benchmark:theme [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--tenant` | `benchmark` | Tenant ID |
| `--domain` | | Domain name |
| `--seed` | | Seed benchmark data first |
| `--pages` | `10000` | Number of pages to generate |
| `--tries` | `100` | Iterations per benchmark |
| `--chunk` | `50` | Rows per bulk insert batch |
| `--unseed` | | Remove benchmark data and exit |
| `--force` | | Run in production |

## Blade Directives

| Directive | Description |
|-----------|-------------|
| `@localDate($date, $format)` | Formats a date using Carbon locale-aware `isoFormat` |
| `@markdown($text)` | Converts Markdown to HTML using GitHub-flavored CommonMark |

## License

MIT
