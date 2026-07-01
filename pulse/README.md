# Pagible Pulse

Laravel Pulse dashboard integration for [Pagible CMS](https://pagible.com) with CMS metrics for content, authentication, AI, search, contact forms, and JSON:API requests.

This package is an optional add-on for the [Pagible CMS monorepo](https://github.com/aimeos/pagible). Install it separately when Laravel Pulse metrics are needed:

```bash
composer require aimeos/pagible-pulse
```

## Configuration

This package requires [Laravel Pulse](https://laravel.com/docs/pulse). When Pulse is installed, the CMS Pulse provider registers CMS recorders and Livewire cards for the published Pulse dashboard.

The default Pulse dashboard gate requires the authenticated user to have the `pulse:view` CMS permission and access to the current tenant. Applications can still define their own `viewPulse` gate before this provider boots to replace the default.

The installer publishes:

| Publish Tag | Target | Description |
|-------------|--------|-------------|
| `cms-pulse-dashboard` | `resources/views/vendor/pulse/dashboard.blade.php` | Pulse dashboard containing CMS cards |
| `cms-pulse-views` | `resources/views/vendor/cms-pulse` | Shared Blade view for CMS Pulse cards |

## Metrics

| Card | Metrics |
|------|---------|
| Pages | Page save, bulk, publish, purge, and related content actions |
| Elements | Element save, bulk, publish, purge, and related content actions |
| Files | File save, bulk, publish, purge, MIME type, and source activity |
| Authentication | Login and authentication events |
| AI | AI mutations, providers, models, latency, and success rate |
| Search | Search queries, result counts, latency, domains, and languages |
| Contact | Contact form submissions and latency |
| JSON:API | JSON:API actions, includes, domains, and latency |

## Commands

### cms:install:pulse

Installs the Pagible Pulse package.

```bash
php artisan cms:install:pulse
```

Publishes the CMS Pulse dashboard and shared card view. If Laravel Pulse is not installed, the command warns that CMS Pulse cards will remain inactive.

## License

MIT
