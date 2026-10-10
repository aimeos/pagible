# Pagible Import

CMS importers for [Pagible CMS](https://pagible.com). Import content from
WordPress and TYPO3 installations.

For installation, use:

```bash
composer require aimeos/pagible-import
```

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

**Caution:** The importers are all beta. Backup your data before you use it and use
at your own risk!

## cms:t3-import

Imports a TYPO3 page tree and its content into Pagible CMS. The importer
supports stock TYPO3 content and Bootstrap Package accordions and carousels.

### Command

```bash
php artisan cms:t3-import \
    --connection=typo3 \
    --domain=example.org \
    --theme=your-theme \
    --file-base=https://example.org/fileadmin
```

| Option | Default | Description |
|--------|---------|-------------|
| `--connection` | `typo3` | Database connection name for the TYPO3 database |
| `--domain` | Auto-detected | Domain name, optionally followed by its root page UID; repeatable |
| `--lang` | `en` | Language code for imported content |
| `--tenant` | | Tenant ID for multi-tenant setups |
| `--editor` | `t3-import` | Editor name for imported records |
| `--theme` | | Pagible theme assigned to imported pages |
| `--file-base` | Domain `/fileadmin` URL | Base URL or local path for TYPO3 files |
| `--page` | | Import or update only this TYPO3 page UID; repeatable |
| `--language` | From `sys_language` | TYPO3 language UID imported as translation, e.g. `1:de` or `2:fr:https://example.fr/`; repeatable |
| `--dry-run` | | Show selected pages without making changes |

Use `--page` to re-import individual pages for page tree with root UID "1":

```bash
php artisan cms:t3-import \
    --connection=typo3 \
    --domain=example.org:1 \
    --page=106
```

### TYPO3 Database Connection

Add a TYPO3 database connection to `config/database.php`:

```php
'typo3' => [
    'driver' => 'mysql',
    'host' => env('T3_DB_HOST', '127.0.0.1'),
    'database' => env('T3_DB_DATABASE', 'typo3'),
    'username' => env('T3_DB_USERNAME', 'root'),
    'password' => env('T3_DB_PASSWORD', ''),
],
```

### Supported TYPO3 Content

The importer preserves page hierarchy, redirects, backend-layout ordering,
shared content references and referenced files. It converts:

- Stock header, text, text-with-image, image, HTML and shortcut elements
- Bootstrap Package accordions to Pagible questions
- Bootstrap Package carousels to Pagible slideshows

Unknown content types are imported only when they contain a regular heading or
body text. Extension-specific plugin behavior is not migrated.

### Translations

Translated TYPO3 pages and their content become language variants of the
imported page, with the default language as source. The languages are read
from the `sys_language` table and can be added or overridden with `--language`:

```bash
php artisan cms:t3-import \
    --domain=example.org \
    --language=1:de \
    --language=2:fr:https://example.fr/
```

The value consists of the TYPO3 language UID, the language code and an optional
URL if the translations are on a separate domain. Otherwise, they are stored on
the domain of the source pages. The URLs of the translations are imported as
they are, without adding a language prefix. Translations whose URL is already
used by another page, like a translated root page with the slug `/` on the same
domain, are skipped with a warning, as are translations in languages without a
code.

If the elements of a translation match the source elements (same count, types
and order), they share the source element IDs and the variant starts up to
date. Otherwise, the translation is imported as it is and marked as
"Needs update", so it can be translated again from the source.

Links to other pages in translated content point to their translations. If a
linked page isn't translated, the link points to the page in the default
language, using an absolute URL when the translation is on another domain.

## cms:wp-import

Imports WordPress posts into Pagible CMS as blog article pages.
Re-importing the same domain and post slug updates and republishes the existing
article instead of creating another page.

### Command

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
| `--theme` | | Pagible theme assigned to imported pages and the blog parent |
| `--media-url` | | Media base URL; repeatable, with the first replacing `wp-content/uploads` paths |
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

Media below `wp-content/uploads` or any configured `--media-url` base is
downloaded into Pagible file storage and imported with a published File version.
The first media base also replaces `wp-content/uploads` URLs. Media outside
those locations remains an external URL reference.

## License

MIT
