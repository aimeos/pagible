# Pagible Import

CMS importers for [Pagible CMS](https://pagible.com). Import content from WordPress, and others.

For installation, use:

```bash
composer require aimeos/pagible-import
```

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Commands

### cms:wp-import

Imports WordPress posts into Pagible CMS as blog article pages.

```bash
php artisan cms:wp-import [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--connection` | `wordpress` | Database connection name for the WordPress database |
| `--domain` | | Domain name for imported pages |
| `--lang` | `en` | Language code for imported content |
| `--tenant` | | Tenant ID for multi-tenant setups |
| `--blog-path` | `blog` | URL path of the parent blog page |
| `--blog-name` | `Blog` | Name of the parent blog page |
| `--type` | `blog` | Page type for imported pages |
| `--media-url` | | Base URL for WordPress uploads (replaces `wp-content/uploads` path) |
| `--editor` | `wp-import` | Editor name for imported records |
| `--dry-run` | | Show what would be imported without making changes |

### WordPress Database Connection

Add a WordPress database connection to `config/database.php`:

```php
'wordpress' => [
    'driver' => 'mysql',
    'host' => env('WP_DB_HOST', '127.0.0.1'),
    'database' => env('WP_DB_DATABASE', 'wordpress'),
    'username' => env('WP_DB_USERNAME', 'root'),
    'password' => env('WP_DB_PASSWORD', ''),
],
```

### Supported Content

The importer converts WordPress Gutenberg blocks to Pagible content elements:

- Text and paragraphs
- Headings
- Images and galleries
- Code blocks
- Tables
- Video and audio embeds
- Notice/callout blocks

Featured images and inline media are imported as Pagible File records with published versions.

## License

LGPL-3.0-only
