# Pagible Pulse

Laravel Pulse dashboard integration for [Pagible CMS](https://pagible.com), covering GraphQL, MCP, search, contact forms, page requests, and JSON:API requests.

This package is an optional add-on for the [Pagible CMS monorepo](https://github.com/aimeos/pagible). Install it separately when Laravel Pulse metrics are needed.

## Requirements

- PHP 8.2 or newer
- A working Pagible CMS installation (`aimeos/pagible-core`)
- Laravel Pulse 1.7 or newer, installed automatically as a dependency

## Setup

### 1. Install the package

```bash
composer require aimeos/pagible-pulse
```

Laravel registers the service provider through package discovery.

### 2. Prepare Laravel Pulse

Publish the Pulse configuration and migrations, then create its tables:

```bash
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
php artisan migrate
```

Pulse can be switched off entirely with `PULSE_ENABLED=false`. For higher-traffic sites, configure its ingest driver as described in the [Laravel Pulse documentation](https://laravel.com/docs/pulse).

### 3. Publish the CMS dashboard

```bash
php artisan cms:install:pulse
```

This publishes:

| Publish tag | Target | Description |
|-------------|--------|-------------|
| `cms-pulse-dashboard` | `resources/views/vendor/pulse/dashboard.blade.php` | Pulse dashboard containing the CMS cards |
| `cms-pulse-views` | `resources/views/vendor/cms-pulse` | Shared CMS metric-card view |

The assets can also be published individually:

```bash
php artisan vendor:publish --tag=cms-pulse-dashboard
php artisan vendor:publish --tag=cms-pulse-views
```

### 4. Grant dashboard access

The `viewPulse` gate requires the authenticated user to hold the `pulse:view` CMS permission and have access to the current tenant.

```php
$user->cmsperms = ['admin'];
// or
$user->cmsperms = ['editor', 'pulse:view'];
```

Applications can define their own `viewPulse` gate before this provider boots.

## How metrics are collected

Every monitored CMS interface emits the neutral `Aimeos\Cms\Events\Observed` event from `pagible-core`. One Pulse recorder converts those events into tenant-scoped `cms_<source>` metrics. Pulse is independent of the structured audit log, so `cms.watch.channel` does not need to be enabled.

High-volume page, search, and JSON:API observations use `cms.watch.sample`; audit events apply their sampling independently.

| Card | Metrics |
|------|---------|
| Requests | Page views by path, domain and HTTP status, including latency |
| Search | Search activity by domain and language, including latency |
| Contact | Contact-form submissions and latency |
| JSON:API | Read and search activity by domain, including latency |
| GraphQL | Operations by domain, latency and success rate |
| MCP | Tool calls by domain, latency and success rate |

Metrics are collected from the time the package is installed; existing activity is not backfilled.

## Configuring cards

Publish `config/cms/pulse.php` with the shared CMS config tag:

```bash
php artisan vendor:publish --tag=cms-config
```

Cards are keyed by observation source and rendered in configuration order. Each reads the corresponding `cms_<source>` metric with count, average duration, and maximum duration. The group defaults to `action`; the optional `requires` list hides cards whose producer package is not installed.

```php
'cards' => [
    'graphql' => [
        'title' => 'GraphQL',
        'details' => ['domain'],
        'success' => true,
        'requires' => ['Aimeos\\Cms\\GraphqlServiceProvider'],
    ],

    'custom' => [
        'title' => 'Custom',
        'details' => ['domain'],
    ],
],
```

Emit the matching observation from the producing package:

```php
Watch::observe(
    source: 'custom',
    action: 'import',
    durationMs: $duration,
    dimensions: ['domain' => $domain],
);
```

## Commands

### `cms:install:pulse`

Publishes the CMS Pulse dashboard and shared card view:

```bash
php artisan cms:install:pulse
```

## License

MIT
