# Pagible Pulse

Laravel Pulse dashboard integration for [Pagible CMS](https://pagible.com) with CMS metrics for GraphQL and MCP content operations, authentication, AI, search, contact forms, page requests, and JSON:API requests.

This package is an optional add-on for the [Pagible CMS monorepo](https://github.com/aimeos/pagible). Install it separately when Laravel Pulse metrics are needed.

## Requirements

- PHP 8.2 or newer
- A working Pagible CMS installation (`aimeos/pagible-core`)
- [Laravel Pulse](https://laravel.com/docs/pulse) 1.7 or newer (installed automatically as a dependency)

## Setup

Setting up the dashboard is a two-step process: first prepare Laravel Pulse itself, then install the Pagible Pulse cards on top of it.

### 1. Set up Laravel Pulse

Pulse is pulled in automatically as a dependency of this package, but its storage still needs to be prepared. Run its migrations to create the Pulse tables:

```bash
php artisan migrate
```

Optionally publish the Pulse config to tune its recorders, storage, and ingest driver:

```bash
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
```

Pulse works out of the box using the default database storage. For higher-traffic sites, configure a Redis ingest and run a Pulse worker as described in the [Laravel Pulse documentation](https://laravel.com/docs/pulse):

```bash
php artisan pulse:work
```

Pulse can be switched off entirely with `PULSE_ENABLED=false` (or `pulse.enabled` in the config). When disabled, the CMS recorders stay inactive.

### 2. Install the Pagible Pulse package

Require the package via Composer:

```bash
composer require aimeos/pagible-pulse
```

The service provider is registered automatically through Laravel package discovery. Then run the install command to publish the CMS dashboard and card views:

```bash
php artisan cms:install:pulse
```

This publishes:

| Publish Tag | Target | Description |
|-------------|--------|-------------|
| `cms-pulse-dashboard` | `resources/views/vendor/pulse/dashboard.blade.php` | Pulse dashboard containing the CMS cards |
| `cms-pulse-views` | `resources/views/vendor/cms-pulse` | Shared Blade view rendering the CMS Pulse cards |

You can publish the assets individually at any time:

```bash
php artisan vendor:publish --tag=cms-pulse-dashboard
php artisan vendor:publish --tag=cms-pulse-views
```

### 3. Grant dashboard access

The Pulse dashboard is protected by the `viewPulse` gate. This package defines a default gate that requires the authenticated user to hold the `pulse:view` CMS permission **and** have access to the current tenant.

Grant the permission through the user's `cmsperms` column, either via the `admin` role (which includes all permissions) or by adding `pulse:view` explicitly:

```php
$user->cmsperms = ['admin'];          // full access, includes pulse:view
// or
$user->cmsperms = ['editor', 'pulse:view'];
```

Applications that need different access rules can define their own `viewPulse` gate **before** this provider boots; the package only installs its default gate when no custom one exists.

### 4. Open the dashboard

Visit the Pulse dashboard in the browser (`/pulse` by default) while signed in as a user with `pulse:view` permission. The CMS cards appear once matching activity has been recorded.

## How metrics are collected

The CMS recorders are registered automatically whenever Laravel Pulse is installed and enabled. They hook into the observability events that Pagible CMS emits for content, authentication, AI, search, contact, page-request, and JSON:API actions, so **no additional configuration is required** — you do not need to enable the `cms.watch` log channel for Pulse. Events fire as soon as the recorders are listening.

Because metrics are only captured going forward, the cards stay empty until new CMS activity occurs after installation.

## Metrics

| Card | Metrics |
|------|---------|
| GraphQL | Content operations (pages, elements, files) and authentication performed through the GraphQL admin API, keyed by operation (`page:save`, `auth:login`, …) |
| MCP | Content operations (pages, elements, files) performed through the MCP server, keyed by operation (`page:save`, `file:publish`, …) |
| CLI | Content operations performed through the CLI and importers, keyed by operation (`page:save`, `file:publish`, …) |
| AI | AI mutations, providers, models, latency, and success rate |
| Search | Search queries, result counts, latency, domains, and languages |
| Contact | Contact form submissions, IP addresses, and latency |
| Requests | Page views by path, domain, HTTP status, and latency |
| JSON:API | JSON:API actions, includes, domains, and latency |

Content activity is bucketed per transport in a `cms_<source>` Pulse type (`cms_graphql`, `cms_mcp`, `cms_cli`, …), so a card can be added for any custom source by pointing its `type` at that bucket.

### Configuring the cards

Every card is fully defined in `config/cms/pulse.php` under the `cards` key (publish the CMS config with `php artisan vendor:publish --tag=cms-config`). Reorder the entries to change the dashboard layout, edit an entry to change a card, remove an entry to hide a card, or add an entry to show a new one. A card only appears when the classes listed in its `events` all exist, so cards for packages that are not installed stay hidden even when left in the list.

```php
'cards' => [
    'graphql' => [
        'title' => 'GraphQL',      // card heading
        'type' => 'cms_graphql',   // Pulse metric type (without tenant suffix)
        'group' => 'action',       // key field the rows are grouped by
        'aggregates' => ['count', 'sum'],
        'details' => ['domain', 'mime'],
        'events' => ['Aimeos\\Cms\\Events\\Authed'],
    ],

    'mysource' => [                // declare a card for any custom bucket
        'title' => 'My source',
        'type' => 'cms_mysource',
        'aggregates' => ['count', 'sum'],
        'details' => ['domain', 'mime'],
    ],
],
```

## Commands

### cms:install:pulse

Installs the Pagible Pulse package.

```bash
php artisan cms:install:pulse
```

Publishes the CMS Pulse dashboard and shared card view. If Laravel Pulse is not installed, the command warns that the CMS Pulse cards will remain inactive.

## License

MIT
