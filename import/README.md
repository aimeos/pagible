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
supports stock TYPO3 content and the content elements of the Bootstrap Package.
TYPO3 9 or newer is required because older versions don't store the URLs of
the pages.

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
| `--lang` | `en` | Language code for imported content, otherwise the default language of the site configuration is used |
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

```php
'typo3' => [
    'driver' => 'mysql',
    'host' => env('T3_DB_HOST', '127.0.0.1'),
    'database' => env('T3_DB_DATABASE', 'typo3'),
    'username' => env('T3_DB_USERNAME', 'root'),
    'password' => env('T3_DB_PASSWORD', ''),
],
```

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

## cms:wp-import

Imports WordPress posts into Pagible CMS as blog article pages.
Re-importing the same domain and post slug updates and republishes the existing
article instead of creating another page. Posts without slug use their title
as slug or, if the title contains no characters for a slug, their ID like
WordPress does.

Published, scheduled and private posts are imported. Scheduled posts are
disabled and get a pending version that `cms:publish` publishes at the post's
date. Private posts are published but restricted to the WordPress roles with
the `read_private_posts` capability (from the `<prefix>user_roles` option,
administrators and editors by default) that exist as Pagible access values.
Use `--role` to map WordPress roles to other access values. If frontend access
restrictions aren't set up in Pagible or none of the roles exists, private
posts are imported as disabled pages and reported, like password protected
posts. Pages which become public again in WordPress lose the restriction
unless it was changed in Pagible. Pages of posts from previous imports which
aren't imported any more, e.g. because they were deleted, trashed or
unpublished, are disabled, as are the redirects of their former slugs
and permalinks.

### Command

```bash
php artisan cms:wp-import [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--connection` | `wordpress` | Database connection name for the WordPress database |
| `--prefix` | `wp_` | Prefix of the WordPress tables (`$table_prefix` in `wp-config.php`, e.g. `wp_2_` for a multisite blog) |
| `--domain` | | Domain name for imported pages |
| `--lang` | | Language code for imported content, by default the language of the WordPress site (`WPLANG` option, e.g. `de` for `de_DE`) or `en` |
| `--tenant` | | Tenant ID for multi-tenant setups |
| `--blog-path` | `blog` | URL path of the parent blog page |
| `--blog-name` | `Blog` | Name of the parent blog page |
| `--type` | `blog` | Page type for imported pages |
| `--theme` | | Pagible theme assigned to imported pages and the blog parent |
| `--media-url` | | Media base URL; repeatable, with the first replacing `wp-content/uploads` paths |
| `--editor` | `wp-import` | Editor name for imported records |
| `--index` | | Import the pages as indexable even if search engines are discouraged from indexing the WordPress site (`blog_public`) |
| `--role` | | WordPress role imported as access value of private posts with another name, e.g. `editor:staff`; repeatable |
| `--since` | | Only import posts changed since the date (e.g. `2026-01-31`) and posts using reusable blocks changed since then, the pages of the others are kept |
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

Don't set a `prefix` for the connection, use the `--prefix` option instead.

### Supported Content

The importer converts WordPress Gutenberg blocks to Pagible content elements:

- Text and paragraphs with their line breaks, including nested and reversed lists, quotes with their citation and verses, also next to images; single newlines of classic content are line breaks like in WordPress
- Lists numbered with letters or roman numbers and superscript or subscript text, kept as HTML
- Separators, imported as horizontal lines
- Headings, links to block anchors like `#intro` point to the imported elements, also from other posts like `/my-post/#intro`; blocks with anchors stay separate elements and keep their element IDs on re-imports; links inside headings are removed and reported
- Images and galleries, custom image links are added as text links and gallery captions become the slideshow title
- File blocks, imported as downloadable files named like their link
- Cover and media-text blocks with image, video or color background, their headings become bold text
- Buttons and social links, imported as links; like WordPress, buttons without text are left out, social links to email addresses get `mailto:`, the ones without scheme `https://` and the ones without label the name of the service like "LinkedIn"
- Code and preformatted blocks, the language of the code is taken from SyntaxHighlighter blocks and from the `language-*` class, the `brush` class or the `lang` attribute of the code
- Tables with their captions, header and footer rows, links and formatting in cells; cells spanning several columns or rows are followed by empty cells
- Video and audio embeds, YouTube and Vimeo videos are embedded by their players like by WordPress, other embeds become links
- Details blocks and `<details>` in classic content, the bold summary is followed by the hidden content, which is always visible and converted like the other blocks
- Layout blocks of plugins like the rows and columns of Kadence or Spectra, their inner blocks are imported like the ones of groups
- Deprecated text columns blocks, imported as text
- Tabs blocks, each tab panel becomes its bold label followed by its content like details blocks
- Playlist blocks, each track with a source URL becomes an audio file named by its title and artist, followed by the caption
- Accordion blocks with text answers, imported as questions; accordions with other answers become the bold questions followed by their answers like details blocks
- Math blocks, whose LaTeX source is kept as code because formulas can't be shown
- Footnotes, added as a numbered list where the footnotes block is
- Reusable blocks (synced patterns), replaced by their content including the overrides of the post (texts, list items, links, images and captions) for the blocks and attributes allowing overrides in the pattern, also from the `overrides` attribute of older block editor versions; unpublished and password protected ones are skipped and reported like WordPress hides them
- Paragraphs, headings, list items, buttons, images and image captions bound to custom fields of the post (block bindings of WordPress 6.5+), the values of the fields replace the saved content; like WordPress, protected fields starting with `_` aren't used, empty fields replace the saved content too (images with an empty URL and buttons with an empty text are left out) and captions only replace an existing caption, as WordPress doesn't add one to images saved without it; the saved content is only kept if the post has no value for the field
- Block names with the `core/` namespace like `<!-- wp:core/paragraph -->`, which are the same blocks for WordPress
- Notice/callout blocks
- `[caption]`, `[gallery]`, `[playlist]`, `[embed]`, `[video]` and `[audio]` shortcodes, `[gallery]` and `[playlist]` without IDs show the files attached to the post, the images of `[gallery]` are sorted by its `orderby` and `order` attributes like by WordPress, `[video]` with YouTube or Vimeo URLs embeds their players, `[embed]` uses the `src` attribute if it has no content and plays the URLs of audio and video files like embeds, `[video]` and `[audio]` without URL show the first attached video or audio file and are reported if there's none, like in WordPress only the `src` attribute and the attributes of the file extensions played by WordPress (`mp4`, `m4v`, `webm`, `ogv` and `flv` for videos, `mp3`, `ogg`, `flac`, `m4a` and `wav` for audio) are used if the URL ends with that extension, and URLs in `src` of other file types become links
- `[code]` and `[sourcecode]` shortcodes of WordPress.com and SyntaxHighlighter become code with the language of their `language` or `lang` attribute
- Classic images aligned left or right, shown next to the following paragraphs
- Small inline images within text like icons, imported as files and kept as HTML referring to the stored files, reported
- Paginated posts (`<!--nextpage-->`), the pages are shown one after another
- YouTube and Vimeo URLs on their own line or in their own paragraph, including mobile, short, live, old `youtube.com/v/` and player URLs, are embedded by their players, URLs of audio and video files ending with the extensions above are played like by WordPress
- Iframes in Custom HTML blocks and classic content are embedded, the ones without web URL are removed
- JPEG, PNG, GIF, WebP, AVIF and SVG images

Blocks rendered dynamically by WordPress, like latest posts or archives, have
no stored content and are skipped with a warning. Query loop, terms query and comments
blocks are skipped with a warning too, because their inner blocks only show the posts,
terms and comments found by WordPress, like the text for no results. Shortcodes of plugins are kept
as text and reported too, as are forms, scripts and other HTML removed for
security reasons. HTML tags in post titles are removed.

Links to other posts point to their imported pages. Supported are links by ID
like `?p=5`, by slug like `?name=my-post` and permalinks whose last path segment is the slug of a post, on
the URLs of the `home` and `siteurl` options or without domain. If the
`permalink_structure` option contains the post ID like `/archives/%post_id%`,
posts are found by their ID too. Links using former slugs of renamed posts
point to their current pages, as do links to the comment pages, embeds and
trackbacks of posts like `/my-post/comment-page-2/`. Their anchors point to the elements of the blocks, while `#comments`, `#respond`, `#comment-12` and `#more-5` stay unchanged. Links to the comment
feeds of posts like `/my-post/feed/` stay unchanged. Links to category and tag archives, also
below the `category_base` and `tag_base` options, stay unchanged. Links to posts
which aren't imported, like WordPress pages, stay unchanged and are reported.

The text before the "more" tag or the manual excerpt becomes the introduction
of the article. Like WordPress excerpts, it leaves out images, embeds, code and
footnote references. Generated introductions longer than 300 characters are
cut at a word boundary and end with "…". Like WordPress, the text before the "more" tag
is left out of the page content if the post hides it on its own page ("Hide the excerpt on the full content page" or
`<!--noteaser-->`) and the "more" tag is on the first page of paginated posts. Non-ASCII slugs, which WordPress stores URL encoded, are
decoded for the page URLs.

The meta data of the pages is taken from Yoast SEO and Rank Math if they are
active (`active_plugins` and `active_sitewide_plugins`, all plugins with data if
the options aren't available; the settings of deactivated plugins stay in the
database but aren't used, like WordPress does): the SEO title becomes the page title, the meta description, the Open
Graph title and description, the canonical URL and the robots settings are
imported. Variables like `%%title%%`, `%%sep%%` and `%%sitename%%` of Yoast or
`%title%` of Rank Math are replaced like the plugins do, using the site title and
the separator of the plugin settings. The variables of the post like the
categories, tags, primary category (Yoast only, empty if none is chosen), author,
ID, focus keyphrase and the date and modification date (in the `date_format` of the
site or the format of Rank Math's `%date(...)%`) are replaced too; Rank Math's
`%category%` and `%tag%` are the first category and tag by name, its `%modified%`
is the later one of the date and modification date (e.g. for scheduled posts) and
`%post_author%` is the author name like `%name%`; values with other variables, e.g. custom fields
or the current date, are skipped. `%%excerpt_only%%` is only the manual excerpt
and `%%excerpt%%` without manual excerpt is generated from the content like the
plugins do: Yoast shortens the whole text to 156 characters (80 for Japanese) at
the last space, Rank Math uses the paragraph containing the focus keyword or the
first paragraph. Posts without own SEO title or description use
the templates for posts (`title-post`, `metadesc-post` of Yoast and
`pt_post_title`, `pt_post_description` of Rank Math) of the active plugin; like
Rank Math, its description template is only used for posts without manual
excerpt. In Rank Math templates, `%seo_title%` and `%seo_description%` are the
title and excerpt, in the values of the posts (e.g. the Facebook title) they are
the SEO title and description of the page as Rank Math shows them. If
"Capitalize Titles" of Rank Math is enabled, the words of its titles and Open
Graph titles start with an upper case letter like on the site (only words
starting with ASCII letters, as Rank Math does). Posts
without own robots settings use the defaults of the plugins for posts. Canonical URLs to imported posts point to their pages, other
canonical URLs to the WordPress site are removed and reported. Without a meta
description, the introduction is used. The featured image is the social media
image (custom Open Graph images aren't imported). The focus keyphrases of the
plugins and the categories and tags of the posts become the meta keywords,
except the default category. If "Discourage search engines from indexing this
site" is set (`blog_public`), all pages are imported with "noindex, nofollow"
and a warning is shown, use `--index` to ignore the setting, e.g. for the
database of a staging site.

Former slugs of renamed posts (`_wp_old_slug`) become hidden redirect pages to
the current page like WordPress redirects them. If the `permalink_structure`
option isn't the plain slug, e.g. `/%year%/%monthnum%/%postname%/`, the old
permalinks of the current and former slugs become redirects too, so links to
the WordPress site keep working. Like WordPress, `%category%` is the category
with the lowest ID or the primary category of the active SEO plugin, including
its parent categories, and `%author%` the author slug. Permalinks with former
dates of the posts (`_wp_old_date`) are redirected too. Redirects used by
another page are skipped and reported.

Images are matched to their WordPress attachments by the `wp-image-<ID>` class
or by their file name, also if the content uses a resized copy like
`photo-300x200.jpg`, a scaled, rotated or edited copy, so the original image is imported. Featured images and
the images of `[gallery]` shortcodes use the alternative text of the attachment
(`_wp_attachment_image_alt`) as file name, or the attachment title if there's none. HTML entities like
`&amp;` in the sources and alternative texts of images, audio and video files are decoded like by the browser. The file URL is built
from the `_wp_attached_file` meta like WordPress does, the base URL is taken
from the `upload_url_path`, `upload_path` and `siteurl` options. Sites of a
multisite network (e.g. `--prefix=wp_2_`) use their `sites/<ID>` directory or
the `files` URL of old networks. The attachment's guid, which keeps the domain
of the upload, is only used if the options table isn't available. Settings
outside of the database aren't known, like the `UPLOADS` and `WP_CONTENT_URL`
constants in `wp-config.php` or plugins moving the media to other servers; use
`--media-url` for them.

Media below `wp-content/uploads`, the uploads URL of the options or any configured
`--media-url` base is downloaded into Pagible file storage and imported with a
published File version. The first media base also replaces the URLs below
`wp-content/uploads` and the uploads URL of the options. Media outside
those locations remains an external URL reference.

## License

MIT
