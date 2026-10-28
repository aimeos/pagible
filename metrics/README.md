# Pagible Metrics

Page metrics and keyword research for [Pagible CMS](https://pagible.com). It adds:

- a **Metrics** tab to the page editor of the admin panel (views, visits, conversions, Google Search
  queries and page speed)
- the `metrics` GraphQL mutation used by the admin panel
- the `get-page-metrics`, `get-keyword-volume` and `get-related-keywords` MCP tools, which are also
  available in the AI chat

Page metrics come from the [Laravel Analytics Bridge](https://github.com/aimeos/laravel-analytics-bridge)
and keyword data from the [Laravel Keywords Bridge](https://github.com/aimeos/laravel-keywords-bridge).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible) and is
installed by default with `aimeos/pagible`.

## Installation

```bash
composer require aimeos/pagible-metrics
php artisan cms:install:metrics
```

The install command publishes the analytics bridge configuration. Configure the analytics and
keyword drivers as described in the documentation of both bridge packages. For keyword research,
install one of the drivers:

```bash
composer require aimeos/laravel-keywords-google
# or aimeos/laravel-keywords-ahrefs, aimeos/laravel-keywords-semrush
```

## Permissions

Users need the `page:metrics` permission to see the Metrics tab and to use the GraphQL mutation and
the MCP tools.

## Admin panel

The Metrics tab is an admin panel plugin. After updating the package, publish its assets again:

```bash
php artisan vendor:publish --tag=cms-admin --force
```
