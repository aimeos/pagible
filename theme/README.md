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
| `ttl` | `CMS_THEME_TTL` | `86400` (or `0` in debug) | Time-to-live for cached pages in seconds; `0` disables caching |
| `disk` | `CMS_THEME_DISK` | | Filesystem disk for tenant-uploaded themes; disabled if unconfigured |
| `sitemap` | `CMS_SITEMAP` | `sitemap` | URL path prefix for XML sitemap (`/{sitemap}.xml`) |
| `pageroute` | `CMS_PAGEROUTE` | `{}` | JSON object with catch-all page route options (Laravel route group) |

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

LGPL-3.0-only
