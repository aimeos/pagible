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
| `cache` | `CMS_THEME_CACHE` | `file` (or `array` in debug) | Cache store for rendered pages (from `config/cache.php`) |
| `lock` | `CMS_THEME_LOCK` | `5` | Complete-page render lock lifetime in seconds |
| `stale` | `CMS_THEME_STALE` | `10` | Seconds an expired complete page remains available during revalidation |
| `ttl` | `CMS_THEME_TTL` | `86400` (or `0` in debug) | Time-to-live for cached pages in seconds; `0` disables caching |
| `disk` | `CMS_THEME_DISK` | | Filesystem disk for tenant-uploaded themes; disabled if unconfigured |
| `sitemap` | `CMS_SITEMAP` | `sitemap` | URL path prefix for XML and news sitemaps (`/{sitemap}.xml`, `/{sitemap}-news.xml`) |
| `pageroute` | `CMS_PAGEROUTE` | `{}` | JSON object with catch-all page route options (Laravel route group) |

### robots.txt

The `/robots.txt` route always advertises the main and news sitemap using the
configured `sitemap` prefix. Fresh Laravel applications also contain a static
`public/robots.txt`, which the development and common production web servers serve
before Laravel routing. If it contains custom rules, first copy them into the
`robots.txt` configuration element on a root page and publish the change. Then
remove the static file to make the dynamic route reachable:

```bash
rm public/robots.txt
```

Alternatively, configure the web server to pass `/robots.txt` to Laravel even when
the static file exists.

Pagible emits the published rules before the generated sitemap declarations and
avoids duplicating either built-in sitemap URL when it is already present in the
configured text. Invalid UTF-8, unsupported control characters and content exceeding
the 500 KiB crawler interoperability limit are ignored while the generated sitemap
declarations remain available. The schema limit provides early editor feedback in
characters; the public controller separately enforces the response budget in encoded
bytes. Responses include an ETag and support conditional requests.

### Authenticated page caching

Anonymous public pages use complete-response caching owned entirely by the pre-session middleware. It reads cached responses, coordinates rendering, and stores only a final response marked public. Requests carrying the Laravel session cookie or an `Authorization` header bypass that cache and authenticated responses are rendered privately. Applications with other authentication indicators can extend the cheap pre-session check:

The outer `Origin` middleware applies this policy to every theme route and shares its decision with `ServeCachedPage`. Cache admission requires the request scheme and port to match `APP_URL`; in single-domain mode its hostname must match as well. Pagible still passes noncanonical requests to the application, but forces their responses to `private, no-store` and removes `Expires` so neither its complete-page cache, sitemap responses, nor a compliant shared cache stores them. Configure trusted proxies before this middleware so Laravel resolves the public origin correctly. Pagible does not reject unknown hosts; applications should separately enable Laravel's `TrustHosts` middleware or enforce an equivalent host allowlist at the web server, especially when multidomain routing accepts tenant hosts outside `APP_URL`.

In multi-tenant applications, tenant initialization must run before `ServeCachedPage`; otherwise the middleware can read a cache key and query pages without the intended tenant context. Apply the tenancy initializer globally before the CMS routes or add it to the outer `pageroute.middleware` group. Do not place it in `web` or after `ServeCachedPage`. The core package's Stancl tenancy section contains a configuration example.

```php
\Aimeos\Cms\Http\Middleware\ServeCachedPage::bypassUsing(
    fn($request) => $request->hasCookie('sso')
);
```

CDNs must apply the same bypass rules for the session cookie, authorization header, and any custom authentication indicator; otherwise the edge may return public HTML before Laravel receives the request. Multi-node installations must configure a shared lock-capable theme cache store such as Redis so all application instances address the same entries.

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

After page publication, deletion, or access changes commit, core dispatches a
lightweight event and the theme removes the affected rendered HTML synchronously
through Laravel's configured cache repository without adding queue traffic.
Invalidation failures are reported without undoing committed content. The
origin cache TTL and CDN `s-maxage` remain the consistency boundary, so stale
HTML already stored by an external cache may remain visible until expiry.
Installations using only the core package remain independent of frontend caching.

### Content Security Policy

CSP directives are configured under the `csp` key, with defaults for hCaptcha and OpenStreetMap embeds:

| Option | Env Variable | Default |
|--------|-------------|---------|
| `csp.media-src` | `CMS_CSP_MEDIA_SRC` | |
| `csp.style-src` | `CMS_CSP_STYLE_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |
| `csp.frame-src` | `CMS_CSP_FRAME_SRC` | `https://hcaptcha.com https://*.hcaptcha.com https://www.openstreetmap.org` |
| `csp.script-src` | `CMS_CSP_SCRIPT_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |
| `csp.connect-src` | `CMS_CSP_CONNECT_SRC` | `https://hcaptcha.com https://*.hcaptcha.com` |

## Custom content elements

Your application can add its own element types next to the bundled ones. Describe them in a `schema.json` file, e.g. `resources/cms/schema.json`:

```json
{
    "label": "App",
    "content": {
        "promo": {
            "label": "Promotion",
            "group": "app",
            "fields": {
                "title": {"type": "string", "label": "Title", "required": true, "max": 100},
                "text": {"type": "markdown", "label": "Text"},
                "file": {"type": "image", "label": "Image"},
                "action": {"type": "hidden", "value": "\\App\\Cms\\Promotions"}
            }
        }
    }
}
```

Register the schema and its views in the `boot()` method of a service provider:

```php
use Aimeos\Cms\Schema;
use Illuminate\Support\Facades\View;

Schema::register( resource_path( 'cms' ), 'app' );
View::addNamespace( 'app', resource_path( 'cms/views' ) );
```

Element types are stored with the schema name as prefix (`app::promo`) and rendered by the first existing view of `app::promo`, `cms::promo` and `cms::invalid`. The view `resources/cms/views/promo.blade.php` gets:

| Variable | Content |
|----------|---------|
| `$data` | Element fields as object, e.g. `$data->title` |
| `$files` | Files of the page keyed by ID, e.g. `cms( $files, $data->file?->id ?? null )` |
| `$action` | Return value of the action class or `null` |
| `$page`, `$theme`, `$nav` | Current page, theme name and navigation of the layout |

```blade
<section class="promo">
    <h2>{{ $data->title }}</h2>
    @markdown($data->text ?? '')
</section>
```

Available field types are `audio`, `autocomplete`, `checkbox`, `color`, `combobox`, `date`, `file`, `hidden`, `html`, `image`, `images`, `items`, `map`, `markdown`, `media`, `number`, `plaintext`, `radio`, `range`, `select`, `slider`, `string`, `switch`, `table`, `text`, `url` and `video`. Fields accept `label`, `hint`, `required`, `default`, `min`, `max`, `step`, `pattern`, `options`, `multiple` and `translate` (`false` excludes the field from AI translation), while `items` fields contain their nested fields in `item`. See `theme/schema.json` for examples.

Fields with unknown types aren't shown in the admin panel, so check your schemas after changing them:

```bash
php artisan cms:schema:check app
```

The class of the hidden `action` field is resolved by `app()->call()` with the `page` and the element `item`, so the content can come from your application:

```php
namespace App\Cms;

use Aimeos\Cms\Models\PageView;

class Promotions
{
    public function __invoke( PageView $page, object $item ): array
    {
        return \App\Models\Promotion::active()->limit( 3 )->get()->all();
    }
}
```

Anonymous responses are stored in the page cache for the `cache` minutes of the page, so set `cache` to `0` for pages whose action results must always be current.

A schema without `types` only adds elements and isn't offered as theme in the admin panel.

## Building a theme

A theme is a Composer package with a `schema.json` that also defines the page `types` and their content `sections`:

```
my-theme/
├── composer.json        PSR-4 autoload and Laravel provider discovery
├── schema.json          label, types, content, meta and config definitions
├── public/              CSS, JS and images, published to public/vendor/cms/mytheme/
├── views/
│   ├── layouts/main.blade.php   Main layout, extended by all page type layouts
│   └── hero.blade.php           Overrides the core hero element
├── database/seeders/MythemeDemo.php   Optional demo content for cms:demo
└── src/MythemeServiceProvider.php
```

```php
namespace Aimeos\Cms;

class MythemeServiceProvider extends ThemeProvider
{
    protected string $name = 'mytheme';
}
```

`ThemeProvider` registers the schema and the `mytheme` view namespace, loads the JSON translations of an optional `lang/` directory, registers the admin catalog in `public/i18n/` and publishes `public/` with the `cms-theme` tag. Override `boot()` and call `parent::boot()` for additional services.

- Pages of type `blog` use the first existing layout of `mytheme::layouts.blog`, `cms::layouts.blog` and `cms::layouts.page`, and the core layouts extend `mytheme::layouts.main`
- Main layouts render `<x-cms::head :page="$page" :nav="$nav" />` (charset, viewport, theme color, CSRF token, Content-Security-Policy (built from the `cms.theme.csp.*` settings and the hashes of the custom styles and scripts of the page, omitted if `app.debug` is enabled), title, canonical URL, meta elements, favicon and the `--pico-*` variables of the `theme` config) in `<head>` and `cms::layouts.foot` at the end of `<body>`, `cms::elements` renders a list of content elements in sections
- The theme color is the configured or default background color, `theme-color="#0B1530"` sets a fixed one
- The `--pico-*` variables are pushed to the `head` stack, so the layout must render `@stack('head')` after its stylesheets
- Themes with light and dark mode define a `theme-dark` config with the dark colors, the colors of the `theme` config then only apply to the light mode
- Views with the name of a core element (e.g. `hero.blade.php`) replace it for pages using the theme
- `cmstheme( $page, 'hero.css' )` returns the versioned URL of the theme asset and falls back to the core theme file
- `Database\Seeders\MythemeDemo` extends `Database\Seeders\AbstractDemo` and provides the demo pages created by `cms:demo`

The bundled themes in `themes/` are complete examples.

## Commands

### cms:install:theme

Installs the Pagible Theme package.

```bash
php artisan cms:install:theme
```

Publishes theme files and adds hCaptcha configuration to `config/services.php`. Requires `HCAPTCHA_SITEKEY` and `HCAPTCHA_SECRET` environment variables for contact form spam protection.

### cms:schema:check

Checks the schemas of the registered themes, or only of the given ones, for unknown field types, `select` and `radio` fields without options, `autocomplete` fields without data source, missing action classes and element types without view.

```bash
php artisan cms:schema:check
php artisan cms:schema:check app estate
```

The command exits with code 1 if errors are found, so it can run in your CI pipeline.

## Blade Directives

| Directive | Description |
|-----------|-------------|
| `@element($page, $item)` | Renders a content element or a reference to a shared element with the view of the page theme for its type (falling back to `cms::<type>` and `cms::invalid`), passing its data, the page files and the result of its schema action |
| `@localDate($date, $format)` | Formats a date in the current locale: no format for day and month (e.g. "October 5"), `short`, `medium`, `long` or `full` for ICU date styles, otherwise a Carbon `isoFormat` pattern |
| `@markdown($text)` | Converts Markdown to HTML using GitHub-flavored CommonMark |

## Blade Components

`<x-cms::image>` renders a responsive `<picture>` with `srcset`, lazy loading and the alt text in the page language:

```blade
<x-cms::image :page="$page" :file="$file" />
<x-cms::image :page="$page" :file="$bg" main class="background" sizes="(min-width: 768px) 50vw, 100vw" />
```

| Attribute | Description |
|-----------|-------------|
| `page` | Current page, used for the language and the URLs of private files |
| `file` | File from `$files` (e.g. `cms($files, $data->file?->id ?? null)`) |
| `main` | Main image of the page, loaded eagerly with high priority (default: `false`) |
| `sizes` | Value of the `sizes` attribute (default: `100vw`) |
| `class` | CSS class string or array |

Other attributes like `id` or `data-*` are added to the `<picture>` element. The `cms::pic` partial is kept for existing themes and renders the same component.

`<x-cms::video>` renders a `<video>` with the title in the page language and the last preview image as poster. Without `autoplay`, it shows the player controls and loads nothing until played; with `autoplay`, it plays muted in a loop like an animated image. The slot is the fallback content for browsers without video support:

```blade
<x-cms::video :page="$page" :file="$file">{{ __('Download file') }}</x-cms::video>
<x-cms::video :page="$page" :file="$file" autoplay />
```

`<x-cms::buttons>` renders the buttons of an element (`label`, `url` and `url-rel` each) as links within `<div class="actions">` and skips buttons without a valid URL. Nothing is rendered if the list is empty:

```blade
<x-cms::buttons :buttons="$data->buttons ?? []" />
```

The layout components build the page header from the current `$page` and the `$nav` navigation passed to the layout:

```blade
<nav>
    <ul>
        <li class="brand"><x-cms::brand :page="$page" :nav="$nav" /></li>
    </ul>
    <ul>
        <x-cms::menu :page="$page" :nav="$nav" />
        <x-cms::languages :page="$page" :nav="$nav" />
    </ul>
</nav>
<x-cms::breadcrumb :page="$page" :nav="$nav" />
<x-cms::search :page="$page" />
```

| Component | Output |
|-----------|--------|
| `brand` | Link to the home page with the logo of the nearest page that has one, or the website title. `alternative` prefers the alternative logo (for dark headers), `name` replaces the website title and other attributes like `class` are added to the link |
| `menu` | One `<li>` per menu item, with a dropdown for items with sub-pages. Active links get the `active` class and the current page gets `aria-current="page"`. `:dropdown="false"` renders top-level links only, `parent` adds the parent page as first dropdown link, `highlight="contact"` adds the class `contact` to the item with that path and `link-class` adds a class to all links |
| `languages` | `<li class="languages">` with a dropdown linking all published language variants of the page, only rendered if there are at least two |
| `breadcrumb` | Breadcrumb navigation of the ancestors below the root page, only rendered for pages below the first level |
| `search` | Search dialog `#modal-search` used by the search script of the theme |

## License

MIT
