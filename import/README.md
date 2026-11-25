# Pagible Import

CMS importers for [Pagible CMS](https://pagible.com). Import content from
WordPress, TYPO3 and Statamic installations.

For installation, use:

```bash
composer require aimeos/pagible-import
```

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

**Caution:** The importers are all beta. Backup your data before you use it and use
at your own risk!

## cms:t3-import

<<<<<<< Updated upstream
Imports a TYPO3 page tree and its content into Pagible CMS. The importer
supports stock TYPO3 content and the content elements of the Bootstrap Package.
TYPO3 9 or newer is required because older versions don't store the URLs of
the pages.

### Command
=======
Imports a TYPO3 page tree and its content into Pagible CMS.
>>>>>>> Stashed changes

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
<<<<<<< Updated upstream
| `--domain` | Auto-detected | Domain name, optionally followed by its root page UID; repeatable |
| `--lang` | `en` | Language code for imported content, otherwise the default language of the site configuration is used |
=======
| `--domain` | Auto-detected | Domain name, optionally followed by its root page UID (e.g. `example.org:1`); repeatable |
| `--lang` | `en` | Language code for imported content |
>>>>>>> Stashed changes
| `--tenant` | | Tenant ID for multi-tenant setups |
| `--editor` | `t3-import` | Editor name for imported records |
| `--theme` | | Pagible theme assigned to imported pages |
| `--file-base` | Domain `/fileadmin` URL | Base URL or local path for TYPO3 files |
| `--path` | | Path to the TYPO3 installation for the site configurations and TSconfig files, e.g. `/var/www/typo3` |
| `--group` | `main` | Element group of the content in a TYPO3 column, e.g. `1:sidebar`; repeatable |
| `--page` | | Import or update only this TYPO3 page UID; repeatable |
| `--since` | | Only import the pages changed since the date, e.g. `2026-01-31` |
| `--language` | From `sys_language` | TYPO3 language UID imported as translation, e.g. `1:de` or `2:fr:https://example.fr/`; repeatable |
| `--dry-run` | | Show selected pages without making changes |

<<<<<<< Updated upstream
Use `--page` to re-import individual pages for page tree with root UID "1":

```bash
php artisan cms:t3-import \
    --connection=typo3 \
    --domain=example.org:1 \
    --page=106
```

With `--since`, pages are changed by their own modifications, those of their
content, items and file references, of the pages their content is taken from
(including the targets of shortcut pages used as content source), of the
files and file metadata they use and by content and pages becoming visible
or expiring due to their start and end times.

### TYPO3 Database Connection

Add a TYPO3 database connection to `config/database.php`:
=======
Add the TYPO3 database to `config/database.php`:
>>>>>>> Stashed changes

```php
'typo3' => [
    'driver' => 'mysql',
    'host' => env('T3_DB_HOST', '127.0.0.1'),
    'database' => env('T3_DB_DATABASE', 'typo3'),
    'username' => env('T3_DB_USERNAME', 'root'),
    'password' => env('T3_DB_PASSWORD', ''),
],
```

<<<<<<< Updated upstream
### Site Configurations

With `--path`, the site configurations in `config/sites/*/config.yaml` (or
`typo3conf/sites/` in older versions) are read, which requires the Symfony YAML
component:

```bash
composer require symfony/yaml
```

Pages with a site configuration become root pages using the domain of the site
`base` or of the `baseVariants` entry for the production context. Bases using
environment variables like `%env(BASE)%` can't be resolved, use `--domain` for
them. The languages of the sites are used per site with their default
language (`languageId: 0`) as language of the pages unless `--lang` is given.
The `fallbackType` and `fallbacks` settings of the languages are used for the
translated content and links like in TYPO3. Languages with the same code on the
same domain, like `de_DE` and `de_CH`, use the regional code (`de-CH`) from
`hreflang` or `locale` for all but the first one.

The global PageTSconfig of the extensions (`Configuration/page.tsconfig` and
the `page.tsconfig` files of site sets) and of the sites is also read for the
backend layouts. Without `--path`, only the PageTSconfig of the pages is used.

### Supported TYPO3 Content

The importer preserves page hierarchy, redirects, backend-layout ordering,
shared content references and referenced files. Content in columns which
aren't part of the backend layout isn't shown by TYPO3 and is skipped with a
warning. Content of EXT:container and gridelements containers follows its
container in the order of the container columns. It converts:

- Stock header, text, text-with-image, text-and-media, image, HTML and shortcut elements
- Bullet lists to text (or HTML for definition lists)
- Tables to Pagible tables, links in cells are kept as Markdown links
- Uploads to file elements including the files of folder and static file
  collections in the configured sort order
- Linked images to HTML elements, because image elements can't contain links
- Bootstrap Package accordions and tabs to Pagible questions
- Bootstrap Package carousels (including small and fullscreen) to Pagible slideshows,
  or cards if the slides contain text
- Bootstrap Package galleries to Pagible slideshows, media elements to text with images
  and external media to embedded videos
- Bootstrap Package list groups to lists and quotes to quotes with their linked source
- Bootstrap Package CSV tables to Pagible tables, also from attached CSV files which stay attached to the page
- Bootstrap Package card groups and icon groups to Pagible cards including their icons and button links
- Bootstrap Package timelines to Pagible timelines

Headers are imported like fluid_styled_content renders them: hidden headers
(layout "Hidden") are skipped including subheader and date, subheaders become
headings one level below the header and dates become text. Elements with items
like questions, cards, slideshows and timelines use plain headers as their
title, while headers with another level, link, subheader or date are shown in
front of them.

The first element of each content element gets an ID derived from the UID of
the TYPO3 content element (or of the one in the default language for
translations), so links to TYPO3 content anchors like `#c123`,
`t3://page?uid=5#c123` and `t3://page?uid=current#c123` point to the imported
element. Pages unchanged since a previous import keep their element IDs.

Like TYPO3's `lib.parseFunc_RTE`, lines of rich text which aren't inside a
block element like `<p>`, `<ul>` or `<table>` become paragraphs, e.g. text
saved by older editors or without rich text editor. Empty lines are dropped
instead of becoming empty paragraphs. Links of headers and linked images keep
the `_blank` target of their link field (popup windows too), while card and
quote links can't have a target in Markdown. Link fields in the legacy
notation TYPO3 still resolves, like `5`, `5#c3`, `5,3`, `file:12`,
`info@example.com` or `www.example.com`, are converted like `t3://` links, and
links with other schemes than `http`, `https`, `mailto` and `tel`, e.g.
`javascript:`, are dropped. The language of the additional parameters, like
`&L=1` in `t3://page?uid=5 - - - &L=1`, is used for page links, other
additional parameters are ignored.

Image crop areas are applied to images downloaded via `--file-base` URLs, and
the alternative texts or captions become the file descriptions in the language
of the reference. Dividers are skipped. Plugins, forms, login forms and menus,
including the old `mailform`, `login`, `search`, `menu` and `script` types,
can't be imported and only their header is kept, which is reported. Icons of
Bootstrap Package icon sets in text-and-icon elements and icon groups aren't
files and are reported, uploaded icons are imported. Unknown
content types like plugins registered as own content type are imported with
their heading, body text and files only, which is reported. Extension-specific
plugin behavior is not migrated. Files marked as missing in TYPO3 are skipped.

Not imported are the sitemap priority and change frequency of the pages, the
link targets of cards and quote sources, the header position, the table footer
setting, the layout and description settings of uploads and file collections
by category.

### Redirects

When the whole page tree is imported, the redirects of the TYPO3 redirects
module (`sys_redirect`), including the ones TYPO3 10+ creates when the slug of
a page changes, become hidden pages below the root page redirecting to their
target. Only enabled redirects which are active now are imported, the first
one of each URL is used, the redirects of a host before the ones for all hosts
like in TYPO3. Redirects of the domain of a translation are saved
in its language, redirects for all hosts (`*`) are imported for each domain
including the ones of translations, and the ones for other hosts are skipped.
Encoded URLs like `/%C3%BCber` are decoded. Targets like `t3://page?uid=5`,
`t3://file?uid=1` and legacy ones like `5` are resolved, external URLs and
paths are kept. Regular expressions, redirects with query parameters,
redirects of the home page, unresolvable targets and redirects whose URL is
used by another page are skipped and reported. Redirects of previous imports
which were deleted, disabled or expired in TYPO3 since are disabled and
enabled again if they are back. The dry run lists the redirects with their
unresolved targets and the redirects of previous imports it would disable. The status code of the redirects isn't imported, and
imports of selected pages with `--page` don't import redirects.

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
linked page isn't translated, the link points to the translation in the first
fallback language of the site configuration or to the page in the default
language, using an absolute URL when the translation is on another domain.
Links with `_language` or `L` parameters point to the translation in that
language. With site configurations, the language UIDs are mapped per site.
Like in TYPO3, pages hidden in the default language (`l18n_cfg`) are only
linked from their translations and pages hidden if not translated aren't
linked from languages without translation. Translations of pages hidden in the
default language aren't imported.
=======
The importer keeps the page hierarchy, redirects, backend layout order, shared
content and referenced files. It converts stock TYPO3 content elements and
Bootstrap Package accordions and carousels. Other content types are only
imported if they contain a heading or text; plugin behavior isn't migrated.

Translated pages become language variants of the imported pages. Their URLs are
imported as they are, without a language prefix, on the domain of the source
pages unless a URL is given with `--language`. Translations whose URL is
already used by another page are skipped with a warning.
>>>>>>> Stashed changes

## cms:wp-import

Imports WordPress posts into Pagible CMS as blog article pages.

```bash
php artisan cms:wp-import [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--connection` | `wordpress` | Database connection name for the WordPress database |
| `--prefix` | `wp_` | Prefix of the WordPress tables (e.g. `wp_2_` for a multisite blog) |
| `--domain` | | Domain name for imported pages |
| `--lang` | | Language code for imported content, the language of the WordPress site or `en` by default |
| `--tenant` | | Tenant ID for multi-tenant setups |
| `--blog-path` | `blog` | URL path of the parent blog page |
| `--blog-name` | `Blog` | Name of the parent blog page |
| `--type` | `blog` | Page type for imported pages |
| `--theme` | | Pagible theme assigned to imported pages and the blog parent |
| `--media-url` | | Media base URL; repeatable, with the first replacing `wp-content/uploads` paths |
| `--editor` | `wp-import` | Editor name for imported records |
| `--index` | | Import the pages as indexable even if the WordPress site discourages search engines |
| `--role` | | WordPress role imported as access value of private posts with another name, e.g. `editor:staff`; repeatable |
| `--since` | | Only import posts changed since the date (e.g. `2026-01-31`), the pages of the others are kept |
| `--dry-run` | | Show what would be imported without making changes |

Add the WordPress database to `config/database.php` and use `--prefix`
instead of a `prefix` setting for the connection:

```php
'wordpress' => [
    'driver' => 'mysql',
    'host' => env('WP_DB_HOST', '127.0.0.1'),
    'database' => env('WP_DB_DATABASE', 'wordpress'),
    'username' => env('WP_DB_USERNAME', 'root'),
    'password' => env('WP_DB_PASSWORD', ''),
],
```

Published, scheduled and private posts are imported. Scheduled posts are
published by `php artisan cms:publish` at their date, private posts are
restricted to the WordPress roles that can read them. Re-imports update the
existing pages, pages of posts which aren't imported any more are disabled.

The importer converts Gutenberg blocks and classic content including images,
galleries, files, embeds, tables, code, buttons, reusable blocks and the
shortcodes of WordPress. Dynamic blocks like latest posts, query loops,
plugin shortcodes, forms and scripts are skipped and reported. Links to other
posts point to their pages, former slugs and permalinks become redirects. SEO
data of Yoast SEO and Rank Math is imported as meta data.

Media below `wp-content/uploads` or a `--media-url` base is downloaded into the
Pagible file storage, other media stays an external URL. Use `--media-url` if
the uploads are moved by `wp-config.php` constants or plugins.

## cms:statamic-import

Imports the entries of Statamic collections into Pagible CMS pages.

```bash
php artisan cms:statamic-import [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--connection` | `statamic` | Database connection name for the Statamic database |
| `--domain` | | Domain name for the imported pages |
| `--lang` | | Language code for the imported pages, the language of the site or `en` by default |
| `--tenant` | | Tenant ID for multi-tenant setups |
| `--editor` | `statamic-import` | Editor name for imported records |
| `--file-base` | | Base URL of the assets for all containers (e.g. `https://example.com/assets`) or per container (e.g. `images=https://cdn.example.com/images`); repeatable |
| `--container` | | Asset container for asset values without container, the only container or `assets` by default |
| `--collections` | `pages` | Comma-separated list of Statamic collections to import |
| `--fields` | `body,content,article,text,page_builder,page_sections,blocks` | Comma-separated list of the entry fields containing the content |
| `--site` | | Statamic site handle to import, the first site or `default` by default |
| `--language` | | Statamic site imported as page translation, optionally with its language code and the URL of its own domain or its URL path (e.g. `de`, `de:de:/de/` or `fr:fr:https://example.fr/`); repeatable |
| `--since` | | Only import entries changed since the date (e.g. `2026-01-31`), the pages of the others are kept |
| `--dry-run` | | Show what would be imported without making changes |

The importer reads the database of the [Statamic Eloquent driver](https://github.com/statamic/eloquent-driver).
Sites storing their content in flat files must be migrated first with
`php please eloquent:import-entries` and, for the field settings, `php please
eloquent:import-blueprints`. Add the database to `config/database.php`:

```php
'statamic' => [
    'driver' => 'mysql',
    'host' => env('STATAMIC_DB_HOST', '127.0.0.1'),
    'database' => env('STATAMIC_DB_DATABASE', 'statamic'),
    'username' => env('STATAMIC_DB_USERNAME', 'root'),
    'password' => env('STATAMIC_DB_PASSWORD', ''),
    // must match STATAMIC_ELOQUENT_PREFIX of the Statamic site
    'prefix' => env('STATAMIC_ELOQUENT_PREFIX', ''),
],
```

Without `--file-base`, the URLs of the assets are unknown and they aren't
imported.

Entries become pages with their Statamic URLs, structured collections keep the
order and hierarchy of their trees and mounted collections are imported below
their entry. Draft entries aren't imported, private and protected entries
become disabled pages and scheduled entries are published by `php artisan
cms:publish` at their date. Entries with a `redirect` field become redirect
pages. Re-imports update the existing pages, pages of entries which aren't
imported any more are disabled. Navigations, taxonomies, globals, forms and
revisions aren't imported.

The importer converts Bard, Replicator, Markdown, Text, Textarea, HTML, Table,
Code, Group, List, Array, Link, Video and Asset fields using the field settings
of the blueprints. Assets are downloaded into the Pagible file storage, links
to entries point to their pages. Grid fields and Antlers templates aren't
imported. SEO Pro data and `meta_*`/`og_*` fields become the meta data.

Localized entries of the sites given by `--language` become page translations.
The URL path of a site (e.g. `/de/`) prefixes the URLs of its translations.
For site URLs with an Antlers host like `https://{{ env:DE_HOST }}/`, pass the
domain with `--language=de:de:https://example.de/`. Translations whose URL is
already used by another page are skipped with a warning.

**Caution:** Pages of previous imports are disabled if their entries aren't
part of the current run. Use a different `--editor` value for each run if you
import collections in separate runs.

## License

MIT
